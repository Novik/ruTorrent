<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/xmlrpc_proxy.php');

/**
 * Every policy category, decided twice: once as a call of its own and once as
 * the same call inside a system.multicall, compared on all three axes this
 * proxy controls -- what it does, how much it trusts the connection it does it
 * on, and what bytes rtorrent ends up reading. Structural parity, not parity
 * of what the daemon then does with them: see the note under R5.
 *
 * The two entry points into decide() read a call from different positions.
 * The single-call path reads parameter 1 as the URI of a load and parameters
 * 2 and up as commands; the member loop reads a member's parameters from 2,
 * because that is where a multicall's commands begin. Anything a single call
 * is judged on before parameter 2 therefore has no counterpart in a batch
 * unless one is written, and the same is true of the whole elevate branch,
 * which the member loop has no equivalent of at all. Two separate holes of
 * that exact shape have shipped. A per-case test would have caught whichever
 * one it was written for and let the other through, so what is asserted here
 * is a relation between the two paths rather than a list of expected answers.
 *
 * Five relations, over every case the matrix generates:
 *
 *   R1  a call refused alone is refused batched
 *   R2  an argument the single-call path transforms is transformed in the
 *       batch too -- compared per parameter position, by XMLRPC type as well
 *       as decoded value, so a clamp, a normalisation or a re-emission has to
 *       happen on both sides, and <i8>3</i8> does not pass as <string>3</string>
 *   R3  batching never raises trust
 *   R4  batching never drops an argument the single-call path kept
 *   R5  a call sent alone is sent batched -- without it, a batch that refused
 *       every URI it is asked to load would satisfy R1 and pass
 *
 * R1, R5 and R3 are about this proxy's own answer, not rtorrent's. "Sent" here
 * means this side decided to send; trust is a property of the connection, and
 * a batch is one connection carrying every member in it, so a member that
 * would be sent trusted on its own is carried untrusted in a batch and a
 * daemon that enforces the untrusted flag refuses it at inner dispatch. That
 * is deliberate and is not something these relations can state: what they
 * state is that batching is not a way to be answered differently by this side,
 * and R2 that a member reaching rtorrent at all reaches it transformed the way
 * the same call alone would be.
 *
 * R2 compares only the positions both payloads carry. Batching may carry
 * MORE: a command parameter the single-call path strips is copied into the
 * batch instead, because the batch's own commands are the request and
 * dropping one would answer with a short row and no fault. That divergence is
 * deliberate, is why a batch carrying an unrebuilt parameter is forwarded
 * untrusted after refusedInParams() has read it, and is what R4 states the
 * direction of.
 *
 * Both loops match by position, so they assume no earlier position was
 * stripped: stripping one shifts the commands after it down in the single
 * payload but not the batch. Every corpus case carries at most one command,
 * so none shifts; a kept command after a stripped one would read as R2
 * here when it is really the R4 direction.
 *
 * The method lists come off the class by reflection, so a spelling or a
 * signature added to $uriLoadMethods, $sanitizeMethods, $multicallMethods or
 * $elevate is covered here without this file changing. $sizeLimitMax is read
 * the same way, so the boundary cases move with it.
 */
class XMLRPCProxyBatchParityMatrixTest extends TestCase
{
	private $safe = array('d.custom1.set', 'd.custom2.set', 'd.directory.set',
		'd.directory_base.set', 'd.priority.set', 'd.open', 'd.start');

	private $hash = '0123456789abcdef0123456789abcdef01234567';

	/** Divergences found in the family being walked, as readable lines. */
	private $bad;

	/** Cases compared in the family being walked. */
	private $cases;

	private function property($name)
	{
		$property = new ReflectionProperty('XMLRPCProxy', $name);
		$property->setAccessible(true);
		return $property->getValue();
	}

	/* ---------------------------------------------------------------- *
	 * Building the two shapes of the same call
	 * ---------------------------------------------------------------- */

	/**
	 * One parameter. A case gives a bare string, or array(text, type) to pick
	 * the XMLRPC type it arrives as -- base64 above all, which rtorrent
	 * decodes and reads as the same string, so it must not be a way past a
	 * check that reads the string.
	 */
	private function value($param)
	{
		$text = is_array($param) ? $param[0] : $param;
		$type = is_array($param) ? $param[1] : 'string';
		// The base64 envelope written out as given rather than encoded, which
		// is how a case says "base64 that is not base64".
		if($type === 'rawbase64')
			return '<value><base64>' . $text . '</base64></value>';
		// A complete value written by the case, for shapes the scalar helper is
		// deliberately unable to express.
		if($type === 'raw')
			return $text;
		if($type === 'base64')
			$text = base64_encode($text);
		if($type === 'implicit')
			return '<value>' . htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8') . '</value>';
		return '<value><' . $type . '>'
			. htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8')
			. '</' . $type . '></value>';
	}

	private function single($method, $params)
	{
		$xml = '<?xml version="1.0"?><methodCall><methodName>'
			. $method . '</methodName><params>';
		foreach($params as $param)
			$xml .= '<param>' . $this->value($param) . '</param>';
		return $xml . '</params></methodCall>';
	}

	private function member($method, $params)
	{
		$out = '<value><struct>'
			. '<member><name>methodName</name><value><string>' . $method
			. '</string></value></member>'
			. '<member><name>params</name><value><array><data>';
		foreach($params as $param)
			$out .= $this->value($param);
		return $out . '</data></array></value></member></struct></value>';
	}

	private function batch($members)
	{
		return '<?xml version="1.0"?><methodCall>'
			. '<methodName>system.multicall</methodName>'
			. '<params><param><value><array><data>' . implode('', $members)
			. '</data></array></value></param></params></methodCall>';
	}

	private function batched($method, $params)
	{
		return $this->batch(array($this->member($method, $params)));
	}

	private function decide($xml, $allowLocalPaths)
	{
		return XMLRPCProxy::decide($xml, 'sanitize', $this->safe, $allowLocalPaths,
			array('directory' => array('root' => '/', 'resolve' => null)));
	}

	/* ---------------------------------------------------------------- *
	 * Reading a decision back
	 * ---------------------------------------------------------------- */

	/**
	 * The string rtorrent reads out of one parameter, whichever type carries
	 * it -- the same reading extractParamValue() does, so that a value
	 * re-emitted as <i8> and the <string> it arrived as compare equal and a
	 * base64 one compares as what it decodes to.
	 */
	private function text($value)
	{
		if(isset($value->base64))
		{
			$decoded = base64_decode((string)$value->base64, true);
			return ($decoded === false) ? '' : $decoded;
		}
		if(isset($value->string))
			return (string)$value->string;
		// <i8>, <int>, <i4>, <boolean>: the text sits in the type element, and
		// SimpleXML's cast reads only the text directly inside the element it
		// is given, so an untyped cast of <value> would read every one of
		// these as empty.
		foreach($value->children() as $child)
			return (string)$child;
		return trim((string)$value);
	}

	/**
	 * The XMLRPC type that text arrives in, which the decoded value alone
	 * does not say: <string>3</string>, <i8>3</i8> and a base64 decoding to
	 * "3" all read as "3", while only one of them is the integer rtorrent is
	 * being handed. A value with no type element around it is the implicit
	 * string form, which xmlrpc-c reads as <string>.
	 */
	private function typeOf($value)
	{
		foreach($value->children() as $child)
			return $child->getName();
		return 'string';
	}

	/**
	 * The argument values a decided payload ends up putting in front of
	 * $method, read the same way out of a top-level call and out of a
	 * system.multicall member so the two can be compared at all. Null when the
	 * payload does not carry that call, which is what a refusal looks like.
	 */
	/** One argument as both halves of what rtorrent is handed. */
	private function argument($value)
	{
		return array('type' => $this->typeOf($value), 'value' => $this->text($value));
	}

	private function arguments($payload, $method)
	{
		if(!is_string($payload) || ($payload === ''))
			return null;
		$xml = @simplexml_load_string($payload);
		if($xml === false)
			return null;

		if((string)$xml->methodName === $method)
		{
			$values = array();
			if(isset($xml->params->param))
				foreach($xml->params->param as $param)
					$values[] = $this->argument($param->value);
			return $values;
		}

		if(!isset($xml->params->param->value->array->data->value))
			return null;

		foreach($xml->params->param->value->array->data->value as $member)
		{
			if(!isset($member->struct->member))
				continue;
			$name = null;
			$values = null;
			foreach($member->struct->member as $field)
			{
				if(!isset($field->name))
					continue;
				if((string)$field->name === 'methodName')
					$name = isset($field->value->string)
						? (string)$field->value->string
						: trim((string)$field->value);
				else
				// A member with no arguments carries an empty <data/>, which is
				// zero arguments and not an unreadable member.
				if(((string)$field->name === 'params') &&
					isset($field->value->array->data))
				{
					$values = array();
					if(isset($field->value->array->data->value))
						foreach($field->value->array->data->value as $value)
							$values[] = $this->argument($value);
				}
			}
			if(($name === $method) && ($values !== null))
				return $values;
		}
		return null;
	}

	/* ---------------------------------------------------------------- *
	 * The comparison itself
	 * ---------------------------------------------------------------- */

	private function start()
	{
		$this->bad = array();
		$this->cases = 0;
	}

	/**
	 * One cell of the matrix: the same call decided both ways, checked against
	 * all four relations.
	 */
	private function compare($method, $params, $allowLocalPaths, $label)
	{
		$this->cases++;

		$alone = $this->decide($this->single($method, $params), $allowLocalPaths);
		$batch = $this->decide($this->batched($method, $params), $allowLocalPaths);

		$where = $method . ' ' . $label
			. ($allowLocalPaths ? ' [local paths allowed]' : ' [local paths denied]');

		if(($alone['action'] === 'reject') && ($batch['action'] !== 'reject'))
		{
			$this->bad[] = 'R1 ' . $where . ': alone=reject batched=' . $batch['action'];
			return;
		}

		if($alone['action'] !== $batch['action'])
		{
			$this->bad[] = 'R5 ' . $where . ': alone=' . $alone['action']
				. ' batched=' . $batch['action'];
			return;
		}

		if($batch['trusted'] && !$alone['trusted'])
			$this->bad[] = 'R3 ' . $where . ': batched is trusted where alone is not';

		if(($alone['action'] !== 'send') || ($batch['action'] !== 'send'))
			return;

		$sent = $this->arguments($alone['payload'], $method);
		$carried = $this->arguments($batch['payload'], $method);

		if(($sent === null) || ($carried === null))
		{
			$this->bad[] = 'R2 ' . $where . ': the call is not readable back out of '
				. (($sent === null) ? 'the single' : 'the batched') . ' payload';
			return;
		}

		foreach($sent as $index => $value)
		{
			if(!array_key_exists($index, $carried))
			{
				$this->bad[] = 'R4 ' . $where . ': parameter ' . $index
					. ' is kept alone and dropped batched';
				continue;
			}
			if($carried[$index] !== $value)
				$this->bad[] = 'R2 ' . $where . ': parameter ' . $index
					. ' is ' . $this->showArg($value) . ' alone and '
					. $this->showArg($carried[$index]) . ' batched';
		}
	}

	private function showArg($argument)
	{
		return $argument['type'] . ' ' . $this->show($argument['value']);
	}

	private function show($value)
	{
		$value = str_replace(array("\r", "\n"), ' ', $value);
		if(strlen($value) > 60)
			$value = substr($value, 0, 60) . '...';
		return '"' . $value . '"';
	}

	private function verdict($family)
	{
		$message = $family . ': ' . $this->cases . ' cases, '
			. count($this->bad) . ' divergences';
		if(count($this->bad) > 0)
			$message .= "\n  " . implode("\n  ", array_slice($this->bad, 0, 12))
				. ((count($this->bad) > 12)
					? "\n  ... and " . (count($this->bad) - 12) . ' more' : '');
		$this->assertTrue(count($this->bad) === 0, $message);
	}

	/* ---------------------------------------------------------------- *
	 * The corpora
	 * ---------------------------------------------------------------- */

	/**
	 * What a load.* parameter 1 can be. The network forms are the four
	 * $networkUri accepts; everything else rtorrent opens as a path on its own
	 * filesystem, which is what $allowLocalPaths decides about.
	 */
	private function uris()
	{
		return array(
			'network http'      => 'http://example.invalid/a.torrent',
			'network https'     => 'https://example.invalid/a.torrent',
			'network ftp'       => 'ftp://example.invalid/a.torrent',
			'network magnet'    => 'magnet:?xt=urn:btih:' . str_repeat('a', 40),
			'absolute path'     => '/etc/probe-parity.torrent',
			'relative path'     => 'probe-parity.torrent',
			'dotted path'       => './probe-parity.torrent',
			'parent path'       => '../probe-parity.torrent',
			'session path'      => '/var/lib/rtorrent/session/probe-parity.torrent',
			'double slash'      => '//example.invalid/a.torrent',
			'file scheme'       => 'file:///etc/probe-parity.torrent',
			'uppercase scheme'  => 'HTTP://example.invalid/a.torrent',
			'unknown scheme'    => 'gopher://example.invalid/a.torrent',
			'empty'             => '',
		);
	}

	private function torrent()
	{
		return 'd8:announce20:http://tr.invalid/a4:infod6:lengthi12e4:name8:file.txt'
			. '12:piece lengthi16384e6:pieces20:' . str_repeat("\x01", 20) . 'ee';
	}

	/** A value each shape accepts, and values it must not. */
	private function shapeValues($shape, $sizeLimitMax)
	{
		switch($shape)
		{
			case 'hash':
				return array(
					'good' => array('lowercase hash' => $this->hash,
						'uppercase hash' => strtoupper($this->hash)),
					'bad'  => array('short hash' => substr($this->hash, 1),
						'long hash' => $this->hash . '0',
						'non hex hash' => str_repeat('g', 40),
						'empty hash' => '',
						'hash with a command' => $this->hash . ';execute=/bin/id'));

			case 'empty':
				return array(
					'good' => array('empty target' => ''),
					'bad'  => array('named target' => 'main',
						'space target' => ' '));

			// What an integer may be written as is xmlrpc-c's question, not this
			// side's: a spelling the daemon reads as a number and this side does
			// not is a number that arrives with nothing applied to it. Measured
			// against 1.59.03 -- a sign and leading zeros are part of the
			// spelling, the number of digits is not a limit of its own, and
			// space around it is refused.
			case 'int':
				return array(
					'good' => array('zero' => '0', 'small' => '3',
						'negative' => '-1', 'signed positive' => '+3',
						'leading zeros' => '0003',
						'eighteen digits' => str_repeat('9', 18),
						'int64 max' => '9223372036854775807',
						'int64 min' => '-9223372036854775808'),
					'bad'  => array('above int64' => '9223372036854775808',
						'below int64' => '-9223372036854775809',
						'nineteen nines' => str_repeat('9', 19),
						'fractional' => '1.5', 'alphabetic' => 'high',
						'empty int' => '', 'hex' => '0x3',
						'leading space' => ' 3', 'trailing space' => '3 ',
						'sign alone' => '+', 'two signs' => '++3'));

			case 'size':
				return array(
					'good' => array('one' => '1',
						'under the ceiling' => (string)($sizeLimitMax - 1),
						'at the ceiling' => (string)$sizeLimitMax,
						'over the ceiling' => (string)($sizeLimitMax + 1),
						'far over the ceiling' => '99999999999',
						'signed far over the ceiling' => '+99999999999',
						'zero padded past eighteen characters' => '0000000000000016777217',
						'eighteen digits' => str_repeat('9', 18),
						'int64 max' => '9223372036854775807'),
					'bad'  => array('zero' => '0', 'negative' => '-1',
						'signed zero' => '+0', 'alphabetic' => 'lots',
						'empty size' => '', 'leading space' => ' 1024',
						'above int64' => '9223372036854775808',
						'nineteen nines' => str_repeat('9', 19)));

			case 'text':
				return array(
					'good' => array('plain' => 'a label',
						'punctuated' => 'Movies (2024), imported',
						'quoted' => 'say "hi" now',
						'dollar' => '$execute.capture=/bin/hostname',
						'separators' => 'x;execute=/bin/id',
						'empty text' => ''),
					'bad'  => array());
		}
		return array('good' => array(), 'bad' => array());
	}

	/* ---------------------------------------------------------------- *
	 * The matrix
	 * ---------------------------------------------------------------- */

	/**
	 * Reading the lists off the class covers a method added later only as far
	 * as this file knows what its arguments look like. A shape with no corpus
	 * would be walked with nothing to vary and would pass by testing nothing,
	 * which is the failure this file exists to prevent, so adding one to
	 * $elevate fails here until shapeValues() states what it accepts.
	 *
	 * The subset is the other half of the same invariant: the single-call path
	 * applies the URI rule inside its $sanitizeMethods branch, so an alias
	 * listed as carrying a URI and not as a load would be judged on it in a
	 * batch and not alone -- an asymmetry the other four relations, which only
	 * ever require a batch to be no weaker, would let through.
	 */
	public function testTheListsThisMatrixIsDrivenFromAreUsable()
	{
		$this->start();

		$sizeLimitMax = $this->property('sizeLimitMax');
		$seen = array();
		foreach($this->property('elevate') as $method => $shapes)
			foreach($shapes as $shape)
			{
				if(isset($seen[$shape]))
					continue;
				$seen[$shape] = true;
				$this->cases++;
				$values = $this->shapeValues($shape, $sizeLimitMax);
				if(count($values['good']) === 0)
					$this->bad[] = 'the shape "' . $shape
						. '" has no accepted value in shapeValues(), so every'
						. ' signature that uses it is walked without being tested';
			}

		$sanitizeMethods = $this->property('sanitizeMethods');
		foreach($this->property('uriLoadMethods') as $method)
		{
			$this->cases++;
			if(!in_array($method, $sanitizeMethods, true))
				$this->bad[] = $method . ' carries a URI but is not in $sanitizeMethods,'
					. ' so the single-call path never applies the URI rule to it';
		}

		$this->verdict('the lists this matrix is driven from');
	}

	/**
	 * Every alias that carries a URI at parameter 1, with local paths denied
	 * and allowed. The member loop reads a member from parameter 2, so
	 * parameter 1 is exactly the position a batch had no rule for.
	 */
	public function testEveryUriLoadAliasIsJudgedTheSameBatched()
	{
		$this->start();
		foreach($this->property('uriLoadMethods') as $method)
			foreach(array(false, true) as $allowLocalPaths)
				foreach($this->uris() as $label => $uri)
					foreach(array('string', 'base64') as $type)
						$this->compare($method, array('', array($uri, $type)),
							$allowLocalPaths, $label . ' as ' . $type);
		$this->verdict('every $uriLoadMethods alias, both local-path settings');
	}

	/**
	 * An implicit string carries its whitespace. rtorrent compares the URI
	 * prefix from byte zero, so leading whitespace on a network-looking value
	 * makes it a local path; trimming it only for the policy check would allow
	 * that path through.
	 */
	public function testWhitespaceCannotMakeAnImplicitLocalPathLookLikeANetworkUri()
	{
		foreach($this->property('uriLoadMethods') as $method)
			foreach(array('alone' => $this->single($method,
					array('', array(' http://example.invalid/a.torrent', 'implicit'))),
				'batched' => $this->batched($method,
					array('', array(' http://example.invalid/a.torrent', 'implicit'))))
				as $shape => $xml)
			{
				$decision = $this->decide($xml, false);
				$this->assertTrue($decision['action'] === 'reject', $shape . ' '
					. $method . ' refuses a network-looking local path with leading whitespace');
			}
	}

	/**
	 * The same, with a command parameter behind the URI, so that the position
	 * the batch does read is exercised at the same time as the one it did not.
	 */
	public function testAUriLoadCarryingACommandIsJudgedTheSameBatched()
	{
		$this->start();
		$commands = array('an allowed label' => 'd.custom1.set=tv',
			'an allowed directory' => 'd.directory.set=/torrents/tv',
			'a command not in the list' => 'd.peers_max.set=1');
		foreach($this->property('uriLoadMethods') as $method)
			foreach(array(false, true) as $allowLocalPaths)
				foreach(array('http://example.invalid/a.torrent',
					'/etc/probe-parity.torrent') as $uri)
					foreach($commands as $label => $command)
						$this->compare($method, array('', $uri, $command),
							$allowLocalPaths, $label . ' behind ' . $uri);
		$this->verdict('$uriLoadMethods carrying a command parameter');
	}

	/**
	 * The rest of $sanitizeMethods: the raw family, which carries the torrent
	 * itself at parameter 1, and the 0.9.x spellings, which are deliberately
	 * absent from $uriLoadMethods because they put the URI a parameter
	 * earlier. Neither may be refused for a parameter 1 that is not a URI,
	 * which is what would happen if the new rule were applied by position
	 * alone.
	 */
	public function testTheRestOfSanitizeMethodsIsJudgedTheSameBatched()
	{
		$this->start();
		$uriMethods = $this->property('uriLoadMethods');
		foreach($this->property('sanitizeMethods') as $method)
		{
			if(in_array($method, $uriMethods, true))
				continue;
			foreach(array(false, true) as $allowLocalPaths)
			{
				$this->compare($method,
					array('', array($this->torrent(), 'base64'), 'd.custom1.set=tv'),
					$allowLocalPaths, 'a torrent by value');
				$this->compare($method, array('', '/etc/probe-parity.torrent'),
					$allowLocalPaths, 'a local path at parameter 1');
				$this->compare($method, array('', 'http://example.invalid/a.torrent'),
					$allowLocalPaths, 'a URI at parameter 1');
			}
		}
		$this->verdict('$sanitizeMethods outside $uriLoadMethods');
	}

	/**
	 * Every multicall family, whose commands the member loop does read, as the
	 * control that says the two paths agreed here already.
	 */
	public function testEveryMulticallFamilyIsJudgedTheSameBatched()
	{
		$this->start();
		$commands = array('an allowed label' => 'd.custom1.set=tv',
			'a read command' => 'd.name=',
			'an allowed directory' => 'd.directory.set=/torrents/tv');
		foreach($this->property('multicallMethods') as $method)
			foreach(array(false, true) as $allowLocalPaths)
				foreach($commands as $label => $command)
					$this->compare($method, array($this->hash, 'main', $command),
						$allowLocalPaths, $label);
		$this->verdict('$multicallMethods');
	}

	/**
	 * Every elevate signature, argument by argument: the shapes it accepts,
	 * the shapes it does not, one argument too few and one too many. The
	 * single-call path validates all of this and re-emits the call from what
	 * it validated; a batch had no branch for any of it, so every member
	 * reached rtorrent as the caller wrote it.
	 */
	public function testEveryElevateSignatureIsJudgedTheSameBatched()
	{
		$this->start();
		$sizeLimitMax = $this->property('sizeLimitMax');

		foreach($this->property('elevate') as $method => $shapes)
		{
			$good = array();
			foreach($shapes as $shape)
			{
				$values = $this->shapeValues($shape, $sizeLimitMax);
				$good[] = reset($values['good']);
			}

			// Every accepted value of every argument, one argument varying at
			// a time, so a signature of three shapes does not need the product
			// of its corpora to have each of them read.
			foreach($shapes as $index => $shape)
			{
				$values = $this->shapeValues($shape, $sizeLimitMax);
				foreach($values['good'] as $label => $value)
				{
					$params = $good;
					$params[$index] = $value;
					$this->compare($method, $params, false,
						'argument ' . $index . ' ' . $label);
				}
				foreach($values['bad'] as $label => $value)
				{
					$params = $good;
					$params[$index] = $value;
					$this->compare($method, $params, false,
						'argument ' . $index . ' ' . $label);
				}
			}

			// Wrong arity, both directions, and every argument sent as base64
			// rather than as a string.
			$this->compare($method, array_slice($good, 0, count($good) - 1), false,
				'one argument too few');
			$this->compare($method, array_merge($good, array('extra')), false,
				'one argument too many');
			$this->compare($method, array(), false, 'no arguments at all');

			$encoded = array();
			foreach($good as $value)
				$encoded[] = array($value, 'base64');
			$this->compare($method, $encoded, false, 'every argument as base64');

			// A number written as the XMLRPC number it is, which is what an
			// ordinary client sends and what rtorrent takes as a value object.
			// SimpleXML reads no text directly inside <value> when a type
			// element is in the way, so a reader that casts the <value> sees
			// every one of these as empty and validates none of them.
			foreach(array('i8', 'int', 'i4') as $type)
			{
				$typed = array();
				$numeric = false;
				foreach($shapes as $index => $shape)
				{
					if(($shape === 'int') || ($shape === 'size'))
					{
						$typed[$index] = array($good[$index], $type);
						$numeric = true;
					}
					else
						$typed[$index] = $good[$index];
				}
				if($numeric)
					$this->compare($method, $typed, false,
						'every number as <' . $type . '>');
			}

			// The implicit string form, <value>text</value>, with no type
			// element around it at all.
			$implicit = array();
			foreach($good as $value)
				$implicit[] = array($value, 'implicit');
			$this->compare($method, $implicit, false, 'every argument implicitly typed');
		}
		$this->verdict('every $elevate signature');
	}

	/**
	 * An elevate member alongside members that are not elevated, which is the
	 * shape a batch actually arrives in and the one where the batch cannot be
	 * trusted as a whole. What the single-call path does to the arguments it
	 * validates still has to have happened.
	 */
	public function testAnElevateMemberIsTransformedAlongsideOtherMembers()
	{
		$this->start();
		$sizeLimitMax = $this->property('sizeLimitMax');

		foreach($this->property('elevate') as $method => $shapes)
		{
			$good = array();
			foreach($shapes as $shape)
			{
				$values = $this->shapeValues($shape, $sizeLimitMax);
				$good[] = reset($values['good']);
			}
			if(in_array('size', $shapes, true))
				$good[count($shapes) - 1] = '99999999999';

			$this->cases++;

			$alone = $this->decide($this->single($method, $good), false);
			$batch = $this->decide($this->batch(array(
				$this->member('d.multicall2', array('', 'main', 'd.name=')),
				$this->member($method, $good),
				$this->member('d.name', array($this->hash)))), false);

			$where = $method . ' among other members';

			if($batch['action'] !== 'send')
			{
				$this->bad[] = 'R1 ' . $where . ': batched=' . $batch['action'];
				continue;
			}
			if($batch['trusted'] && !$alone['trusted'])
			{
				$this->bad[] = 'R3 ' . $where . ': batched is trusted where alone is not';
				continue;
			}

			$sent = $this->arguments($alone['payload'], $method);
			$carried = $this->arguments($batch['payload'], $method);
			if(($sent === null) || ($carried === null))
			{
				$this->bad[] = 'R2 ' . $where . ': the call is not readable back';
				continue;
			}
			foreach($sent as $index => $value)
				if(!array_key_exists($index, $carried))
					$this->bad[] = 'R4 ' . $where . ': parameter ' . $index . ' dropped';
				else
				if($carried[$index] !== $value)
					$this->bad[] = 'R2 ' . $where . ': parameter ' . $index . ' is '
						. $this->showArg($value) . ' alone and '
						. $this->showArg($carried[$index]) . ' batched';
		}
		$this->verdict('$elevate members in a mixed batch');
	}

	/**
	 * The ceiling is the reason $elevate validates a size at all, so it gets
	 * the boundary stated rather than inferred: what leaves the proxy is at
	 * most $sizeLimitMax, whichever shape the call arrived in and whichever
	 * way the number was spelled.
	 *
	 * Stated on both paths rather than compared between them, because the two
	 * read the number with the same code and can therefore miss it together:
	 * every spelling here is one xmlrpc-c reads as an integer, so a size this
	 * side does not recognise is a size the daemon still sets.
	 */
	public function testTheSizeCeilingHoldsAloneAndBatched()
	{
		$this->start();
		$sizeLimitMax = $this->property('sizeLimitMax');

		// Spelling, and the integer xmlrpc-c 1.59.03 reads it as. A sign and
		// leading zeros are integers to the daemon whatever this side makes of
		// them, and nineteen digits is a number rather than a fault.
		$asked = array(
			'1' => 1,
			(string)$sizeLimitMax => $sizeLimitMax,
			(string)($sizeLimitMax + 1) => $sizeLimitMax + 1,
			'99999999999' => 99999999999,
			'+99999999999' => 99999999999,
			'+' . $sizeLimitMax => $sizeLimitMax,
			'0000000000000016777217' => 16777217,
			'009' => 9,
			str_repeat('9', 18) => (int)str_repeat('9', 18),
			'9223372036854775807' => PHP_INT_MAX);

		foreach($this->property('elevate') as $method => $shapes)
		{
			$index = array_search('size', $shapes, true);
			if($index === false)
				continue;

			foreach($asked as $spelling => $number)
			// Whichever type the number arrives in. An over-limit size sent as
			// the plain XMLRPC integer any client writes must leave clamped
			// too, not only one spelled out as a string.
			foreach(array('string', 'i8', 'int', 'base64', 'implicit') as $type)
			{
				$params = array();
				foreach($shapes as $i => $shape)
				{
					$values = $this->shapeValues($shape, $sizeLimitMax);
					$params[$i] = ($i === $index)
						? array((string)$spelling, $type) : reset($values['good']);
				}

				// A validated size leaves as the integer type the single-call
				// path emits, holding the number asked for or the ceiling,
				// whichever is smaller -- and as that number, not as the text
				// it was asked for in.
				$expected = array('type' => 'i8',
					'value' => (string)min($number, $sizeLimitMax));

				foreach(array('alone' => $this->single($method, $params),
					'batched' => $this->batched($method, $params)) as $shape => $xml)
				{
					$this->cases++;
					$where = $method . ' asked for ' . $spelling . ' as <' . $type
						. '> ' . $shape;

					$carried = $this->arguments($this->decide($xml, false)['payload'], $method);
					if($carried === null)
					{
						$this->bad[] = $where . ': not readable back';
						continue;
					}
					if($carried[$index] !== $expected)
						$this->bad[] = $where . ': carries '
							. $this->showArg($carried[$index]) . ', not i8 '
							. $expected['value'];
				}
			}
		}
		$this->verdict('the $sizeLimitMax ceiling, alone and batched');
	}

	/**
	 * A recognised elevated method whose arguments do not match its shape.
	 *
	 * The shape and the ceiling are applied by re-emitting the call from the
	 * values this side read, so a call that cannot be re-emitted has had
	 * neither applied to it. Forwarding it untrusted is not the second control
	 * that would make that safe: on a daemon that reads UNTRUSTED_CONNECTION
	 * and ignores it, forwarding is running. Refused, on both paths.
	 */
	public function testAnElevatedCallThatCannotBeNormalisedIsRefused()
	{
		$this->start();
		$sizeLimitMax = $this->property('sizeLimitMax');

		foreach($this->property('elevate') as $method => $shapes)
		{
			$good = array();
			foreach($shapes as $shape)
			{
				$values = $this->shapeValues($shape, $sizeLimitMax);
				$good[] = reset($values['good']);
			}

			$cases = array('one argument too few' => array_slice($good, 0, count($good) - 1),
				'one argument too many' => array_merge($good, array('extra')),
				'no arguments at all' => array());

			// base64 that is not base64. xmlrpc-c answers a fault for it, so
			// there is no value here to validate and none to send on.
			foreach($shapes as $index => $shape)
			{
				$params = $good;
				$params[$index] = array('%%%%', 'rawbase64');
				$cases['malformed base64 at argument ' . $index] = $params;

				// A value with no scalar reading, and an ambiguous value with two
				// type elements. Neither may be collapsed to an empty string or to
				// whichever child the proxy checks first and then sent as a valid,
				// trusted state-changing call.
				$params = $good;
				$params[$index] = array('<value><array><data><value><string>nested'
					. '</string></value></data></array></value>', 'raw');
				$cases['compound value at argument ' . $index] = $params;

				$params = $good;
				$params[$index] = array('<value><string>first</string>'
					. '<base64>c2Vjb25k</base64></value>', 'raw');
				$cases['two type elements at argument ' . $index] = $params;

				// An implicit string has exactly the same bytes as an explicit
				// string. Whitespace is part of those bytes; trimming it would make
				// an otherwise invalid hash, integer or empty target match.
				if($shape !== 'text')
				{
					$params = $good;
					$params[$index] = array('<value> ' . htmlspecialchars($good[$index],
						ENT_NOQUOTES, 'UTF-8') . ' </value>', 'raw');
					$cases['whitespace around implicit string at argument ' . $index] = $params;
				}

				// xmlrpc-c refuses whitespace in an integer element. The lexical
				// check in integerValue() must see it rather than a trimmed copy.
				if(($shape === 'int') || ($shape === 'size'))
				{
					$params = $good;
					$params[$index] = array(' ' . $good[$index], 'i8');
					$cases['leading whitespace in typed integer at argument ' . $index] = $params;
				}
			}

			foreach($shapes as $index => $shape)
				foreach($this->shapeValues($shape, $sizeLimitMax)['bad'] as $label => $value)
				{
					$params = $good;
					$params[$index] = $value;
					$cases['argument ' . $index . ' ' . $label] = $params;
				}

			foreach($cases as $label => $params)
				foreach(array('alone' => $this->single($method, $params),
					'batched' => $this->batched($method, $params)) as $shape => $xml)
				{
					$this->cases++;
					$decision = $this->decide($xml, false);
					if($decision['action'] !== 'reject')
						$this->bad[] = $method . ' ' . $label . ' ' . $shape . ': '
							. $decision['action'] . ', carrying '
							. $this->show((string)$decision['payload']);
				}
		}
		$this->verdict('$elevate arguments that cannot be normalised');
	}

	/** An implicit string is not a licence to trim the value it carries. */
	public function testAnImplicitTextValueKeepsItsWhitespace()
	{
		$text = "  padded label\t";
		$expected = array('type' => 'string', 'value' => $text);

		foreach(array('alone' => $this->single('d.custom1.set',
				array($this->hash, array($text, 'implicit'))),
			'batched' => $this->batched('d.custom1.set',
				array($this->hash, array($text, 'implicit')))) as $shape => $xml)
		{
			$decision = $this->decide($xml, false);
			$this->assertTrue($decision['action'] === 'send',
				$shape . ' implicit text is sent');
			$arguments = $this->arguments($decision['payload'], 'd.custom1.set');
			$this->assertTrue(isset($arguments[1]) && ($arguments[1] === $expected),
				$shape . ' implicit text keeps its leading and trailing whitespace');
		}
	}

	/**
	 * Bytes a validated value has that XML character data does not.
	 *
	 * Measured against xmlrpc-c 1.59.03: it refuses a document whose <string>
	 * holds bytes that are not UTF-8 or a control character other than tab and
	 * newline, and a carriage return in one comes back out as a newline. So a
	 * value carrying any of those goes out as base64 -- the same bytes, and a
	 * type the daemon reads as the same value -- rather than through
	 * htmlspecialchars(), which answers the empty string for the first of them
	 * and would send a label, or a command, away as nothing.
	 */
	public function testAValueXMLTextCannotHoldKeepsItsBytes()
	{
		$this->start();
		$hash = strtoupper($this->hash);

		$bytes = array('not UTF-8' => "\xC3\x28",
			'a control character' => "a\x01b",
			'a carriage return' => "a\r\nb");

		foreach($bytes as $label => $raw)
		{
			// As the text argument of an elevated call, which rtorrent stores.
			foreach(array('alone' => $this->single('d.custom1.set',
					array($hash, array($raw, 'base64'))),
				'batched' => $this->batched('d.custom1.set',
					array($hash, array($raw, 'base64')))) as $shape => $xml)
			{
				$this->cases++;
				$carried = $this->arguments($this->decide($xml, false)['payload'],
					'd.custom1.set');
				$expected = array('type' => 'base64', 'value' => $raw);
				if(($carried === null) || ($carried[1] !== $expected))
					$this->bad[] = 'd.custom1.set text of ' . $label . ' ' . $shape
						. ': ' . (($carried === null) ? 'not readable back'
							: 'carries ' . $this->showArg($carried[1]));
			}

			// And as a command parameter, where the rebuild is the whole
			// control: quoting each argument is what stops a separator inside
			// one starting a second command, and that only holds for the
			// rebuilt bytes.
			$command = 'd.custom1.set=' . $raw;
			$quoted = 'd.custom1.set="' . $raw . '"';
			$params = array('', 'http://example.invalid/x.torrent',
				array($command, 'base64'));
			foreach(array('alone' => $this->single('load.start', $params),
				'batched' => $this->batched('load.start', $params)) as $shape => $xml)
			{
				$this->cases++;
				$carried = $this->arguments($this->decide($xml, false)['payload'], 'load.start');
				$expected = array('type' => 'base64', 'value' => $quoted);
				if(($carried === null) || !isset($carried[2]) || ($carried[2] !== $expected))
					$this->bad[] = 'load.start carrying ' . $label . ' ' . $shape
						. ': ' . (($carried === null) || !isset($carried[2])
							? 'the parameter is not there'
							: 'carries ' . $this->showArg($carried[2]));
			}
		}
		$this->verdict('values XML text cannot hold');
	}

	/**
	 * A member is a struct, so methodName and params are each one field. A
	 * member naming either of them twice is read by two readers here -- the
	 * policy above and the rebuild below -- and they need not land on the
	 * same field, which is a way to show a policy one URI and rtorrent
	 * another. The matrix cannot generate this: both shapes it builds name
	 * each field once.
	 */
	public function testAMemberNamingAStandardFieldTwiceIsRefused()
	{
		$params = function($uri) {
			return '<member><name>params</name><value><array><data>'
				. '<value><string></string></value>'
				. '<value><string>' . $uri . '</string></value>'
				. '</data></array></value></member>';
		};
		$name = '<member><name>methodName</name><value><string>load.start'
			. '</string></value></member>';

		$once = $this->decide($this->batch(array('<value><struct>' . $name
			. $params('/etc/probe.torrent') . '</struct></value>')), false);
		$this->assertEquals('reject', $once['action'],
			'a local path in the one params field a member has is refused');

		$twice = $this->decide($this->batch(array('<value><struct>' . $name
			. $params('http://example.invalid/safe.torrent')
			. $params('/etc/probe.torrent') . '</struct></value>')), false);
		$this->assertEquals('reject', $twice['action'],
			'a local path in a second params field is refused, not hidden '
			. 'behind the network URI in the first');

		$names = $this->decide($this->batch(array('<value><struct>' . $name
			. '<member><name>methodName</name><value><string>d.name</string>'
			. '</value></member>' . $params('http://example.invalid/x.torrent')
			. '</struct></value>')), false);
		$this->assertEquals('reject', $names['action'],
			'a member naming methodName twice is refused rather than judged '
			. 'as one of the two names');
	}

	/**
	 * A text argument is stored by rtorrent, not parsed, so any bytes are
	 * allowed in one -- but only UTF-8 can go into this document, and asking
	 * for the rest to be escaped answers the empty string. A label or a
	 * comment that is not UTF-8 has to reach rtorrent as the caller sent it
	 * rather than arrive blank.
	 */
	public function testTextThatIsNotUTF8KeepsTheBytesItArrivedWith()
	{
		$raw = "\xC3\x28 not utf-8";
		$hash = strtoupper($this->hash);
		$member = '<value><struct>'
			. '<member><name>methodName</name><value><string>d.custom1.set'
			. '</string></value></member>'
			. '<member><name>params</name><value><array><data>'
			. '<value><string>' . $hash . '</string></value>'
			. '<value><base64>' . base64_encode($raw) . '</base64></value>'
			. '</data></array></value></member></struct></value>';

		$batch = $this->decide($this->batch(array($member)), false);
		$this->assertEquals('send', $batch['action'],
			'a text argument that is not UTF-8 is carried, not refused');
		$carried = $this->arguments($batch['payload'], 'd.custom1.set');
		$this->assertEquals($raw, $carried[1]['value'],
			'the bytes the caller sent are what rtorrent is handed');

		$alone = $this->decide($this->single('d.custom1.set',
			array($hash, array($raw, 'base64'))), false);
		$sent = $this->arguments($alone['payload'], 'd.custom1.set');
		$this->assertEquals($raw, $sent[1]['value'],
			'and the same call on its own is not emptied either');
	}
}
