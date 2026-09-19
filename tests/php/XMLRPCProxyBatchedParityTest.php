<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/xmlrpc_proxy.php');

/**
 * A call is judged the same whether it is sent alone or batched.
 *
 * sanitize mode reads a command-carrying call twice over: on its own it goes
 * through rebuildLoadParams, which keeps the parameters $safeParams names and
 * strips the rest, and only a stripped one is then asked whether it names a
 * refused command. Inside a system.multicall the payload cannot be rebuilt, so
 * every parameter from position 2 was asked that question instead -- including
 * the ones $safeParams names, whose arguments are values rtorrent stores and
 * not commands it runs.
 *
 * refusedCommandName() splits on ',', '$', braces, parens, quotes and
 * whitespace, so the quoting a client puts around a value ends the element and
 * the next word becomes a name. d.custom1.set="catch-up tv" then reads as a
 * call to catch, and d.directory.set="/torrents/my import" as a call to
 * import. Alone both are rebuilt and sent; batched both were refused, and a
 * refusal takes the whole batch, so one label costs every add in it.
 *
 * The parity is the assertion rather than the labels, because the two readings
 * are what diverged: whatever the single-call path is willing to rebuild, the
 * batched path may forward, and it forwards it untrusted where the single-call
 * path sends it trusted. The method lists are read off the class, so a
 * spelling added to either one is covered here without this file changing.
 */
class XMLRPCProxyBatchedParityTest extends TestCase
{
	private $safe = array('d.custom1.set', 'd.custom2.set', 'd.directory.set',
		'd.directory_base.set', 'd.priority.set', 'd.open', 'd.start');
	private $opts;

	public function setUp()
	{
		$this->opts = array('directory' => array('root' => '/', 'resolve' => null));
	}

	private function methodList($name)
	{
		$property = new ReflectionProperty('XMLRPCProxy', $name);
		$property->setAccessible(true);
		return $property->getValue();
	}

	private function value($text, $type = 'string')
	{
		return '<value><' . $type . '>' . htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8')
			. '</' . $type . '></value>';
	}

	/** The call on its own. */
	private function single($method, $params)
	{
		$xml = '<?xml version="1.0"?><methodCall><methodName>' . $method
			. '</methodName><params>';
		foreach($params as $p)
			$xml .= '<param>' . (is_array($p) ? $this->value($p[0], $p[1]) : $this->value($p))
				. '</param>';
		return $xml . '</params></methodCall>';
	}

	/** The same call as the one member of a system.multicall. */
	private function batched($method, $params)
	{
		$member = '<value><struct>'
			. '<member><name>methodName</name><value><string>' . $method
			. '</string></value></member>'
			. '<member><name>params</name><value><array><data>';
		foreach($params as $p)
			$member .= is_array($p) ? $this->value($p[0], $p[1]) : $this->value($p);
		$member .= '</data></array></value></member></struct></value>';

		return '<?xml version="1.0"?><methodCall><methodName>system.multicall</methodName>'
			. '<params><param><value><array><data>' . $member
			. '</data></array></value></param></params></methodCall>';
	}

	private function verdict($xml)
	{
		$d = XMLRPCProxy::decide($xml, 'sanitize', $this->safe, false, $this->opts);
		return $d['action'];
	}

	/**
	 * Parameters 0 and 1 for a method: the load.raw family carries the torrent
	 * at 1, everything else a view or a hash. Commands start at 2 either way.
	 */
	private function leadingParams($method)
	{
		if(strpos($method, 'raw') !== false)
			return array('', array(base64_encode($this->torrent()), 'base64'));
		if(strpos($method, 'load') === 0)
			return array('', 'http://example.invalid/a.torrent');
		return array('0123456789ABCDEF0123456789ABCDEF01234567', 'main');
	}

	private function torrent()
	{
		return 'd8:announce20:http://tr.invalid/a4:infod6:lengthi12e4:name8:file.txt'
			. '12:piece lengthi16384e6:pieces20:' . str_repeat("\x01", 20) . 'ee';
	}

	private function everyMethod()
	{
		return array_merge($this->methodList('sanitizeMethods'),
			$this->methodList('multicallMethods'));
	}

	public function testAnAllowedCommandCarryingOrdinaryTextIsJudgedTheSameBatched()
	{
		// Labels and directories a person types. Each one carries a word that
		// begins with a refused prefix in a position where rtorrent reads a
		// value: catch, import, log., method., schedule, system.env, execute.
		$parameters = array(
			'd.custom1.set="catch-up tv"',
			'd.custom1.set="my import"',
			'd.custom1.set="Movies, imported"',
			'd.custom1.set="scheduled recordings"',
			'd.custom1.set="executed order"',
			'd.custom1.set="log.files"',
			'd.custom1.set="method.actor"',
			'd.custom2.set="system.environment"',
			'd.directory.set="/torrents/my import"',
			'd.directory_base.set="/torrents/catch-up tv"',
		);
		foreach($this->everyMethod() as $method)
		{
			$leading = $this->leadingParams($method);
			foreach($parameters as $parameter)
			{
				$params = array_merge($leading, array($parameter));
				$alone = $this->verdict($this->single($method, $params));
				$batch = $this->verdict($this->batched($method, $params));
				$this->assertTrue($alone === $batch,
					$method . ' with ' . $parameter . ': alone=' . $alone . ' batched=' . $batch);
			}
		}
	}

	public function testARefusedCommandIsStillRefusedBatched()
	{
		// The other half of the same parity: nothing here may be admitted by
		// being batched, and nothing may be admitted by being quoted into an
		// argument that is then called.
		$parameters = array(
			'execute=/bin/id',
			'execute2=/bin/id',
			'import=/etc/passwd',
			'try_import=/etc/passwd',
			'method.insert=x,simple,"d.name="',
			'schedule2=x,0,0,"d.name="',
			'catch=/bin/id',
			'log.open_file=x,/tmp/x',
			'network.scgi.open_port=0.0.0.0:5000',
			'session.path.set=/tmp',
			'directory.default.set=/',
			'system.env=PATH',
			'd.custom1.set="$execute=/bin/id"',
			'd.directory.set="$import=/etc/passwd"',
			'd.multicall=main,execute=/bin/id',
			'cat=$execute=/bin/id',
		);
		foreach($this->everyMethod() as $method)
		{
			$leading = $this->leadingParams($method);
			foreach($parameters as $parameter)
			{
				$params = array_merge($leading, array($parameter));
				$batch = $this->verdict($this->batched($method, $params));
				$this->assertTrue($batch === 'reject',
					'system.multicall carrying ' . $method . ' carrying ' . $parameter
						. ' is refused (' . $batch . ')');
			}
		}
	}

	public function testABatchOfAddsWithOrdinaryLabelsIsNotRefusedForOneOfThem()
	{
		// A batch is refused whole. The shape a client that adds several
		// torrents at once sends, with one ordinary label among them.
		$members = array();
		foreach(array('tv', 'catch-up tv', 'films') as $label)
		{
			$member = '<value><struct>'
				. '<member><name>methodName</name><value><string>load.raw_start'
				. '</string></value></member>'
				. '<member><name>params</name><value><array><data>'
				. $this->value('')
				. $this->value(base64_encode($this->torrent()), 'base64')
				. $this->value('d.custom1.set="' . $label . '"')
				. '</data></array></value></member></struct></value>';
			$members[] = $member;
		}
		$xml = '<?xml version="1.0"?><methodCall><methodName>system.multicall</methodName>'
			. '<params><param><value><array><data>' . implode('', $members)
			. '</data></array></value></param></params></methodCall>';

		$this->assertTrue($this->verdict($xml) === 'send',
			'a batch of three adds is not refused for one of their labels');
	}
}
