<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/xmlrpc_proxy.php');

/**
 * The claim sanitize mode makes is that no caller can name a command in
 * $denyPrefixes through this filter. These are the carriers that claim did not
 * cover: a load.* spelling the method list did not hold, a parameter encoded
 * as base64 rather than written as text, a command nested inside another
 * command's arguments, and a call wrapped in a system.multicall.
 *
 * Each one ends with rtorrent receiving the caller's own bytes on a connection
 * marked untrusted, which is a refusal only from 0.16.10. env_check.php
 * supports 0.9.8 and the whole of 0.16.x, so on every supported version below
 * that the header is read and ignored and this list is the only refusal there
 * is.
 */
class XMLRPCProxyCommandPolicyTest extends TestCase
{
	private $safe = array('d.custom1.set', 'd.directory.set');
	private $opts;

	public function setUp()
	{
		$this->opts = array('directory' => array('root' => '/', 'resolve' => null));
	}

	private function decide($xml)
	{
		return XMLRPCProxy::decide($xml, 'sanitize', $this->safe, false, $this->opts);
	}

	private function call($method, $params = array())
	{
		$xml = '<?xml version="1.0"?><methodCall><methodName>' . $method
			. '</methodName><params>';
		foreach($params as $p)
			$xml .= '<param><value><string>' . htmlspecialchars($p, ENT_NOQUOTES)
				. '</string></value></param>';
		$xml .= '</params></methodCall>';
		return $xml;
	}

	// --- a load.* spelling the method list did not hold ---

	public function testLoadStartVerboseRebuildsLikeLoadStart()
	{
		// load.start_verbose takes the arguments load.start takes and differs
		// only in what it prints. Absent from the list it was not rebuilt, so
		// the command parameter travelled to rtorrent as written.
		$xml = $this->call('load.start_verbose',
			array('', 'http://example.invalid/a.torrent', 'execute=/bin/id'));
		$d = $this->decide($xml);

		$this->assertTrue($d['action'] === 'send',
			'load.start_verbose is still a call this filter forwards');
		$this->assertTrue(strpos($d['payload'], 'execute') === false,
			'the execute parameter is not in what load.start_verbose forwards');
	}

	public function testLoadVerboseIsHeldToTheSameUriRuleAsLoadNormal()
	{
		// load.verbose reads parameter 1 as a URI exactly as load.normal does,
		// and a value that is not a network URI is a path on rtorrent's own
		// filesystem, which becomes the download's tied file.
		$xml = $this->call('load.verbose', array('', '/etc/passwd'));
		$d = $this->decide($xml);

		$this->assertTrue($d['action'] === 'reject',
			'load.verbose from a local path is refused, as load.normal is');
	}

	public function testEveryLoadSpellingRtorrentRegistersIsRebuilt()
	{
		// src/command_events.cc registers these eight, unchanged between 0.9.8
		// and current master. A spelling missing from the list is a spelling
		// whose command parameters are never looked at.
		$spellings = array(
			'load.normal', 'load.start', 'load.verbose', 'load.start_verbose',
			'load.raw', 'load.raw_start', 'load.raw_verbose', 'load.raw_start_verbose',
		);
		foreach($spellings as $method)
		{
			$xml = $this->call($method, array('', 'http://example.invalid/a.torrent',
				'execute=/bin/id'));
			$d = $this->decide($xml);
			$this->assertTrue(($d['action'] === 'reject') ||
				(strpos($d['payload'], 'execute') === false),
				$method.' does not carry an execute parameter to rtorrent');
		}
	}

	// --- a parameter encoded as base64 rather than written as text ---

	public function testABase64CommandParameterIsJudgedDecoded()
	{
		// xmlrpc-c hands rtorrent the decoded bytes, so the command a base64
		// parameter names is the decoded one. Read as text it matches no
		// allowed command, is stripped, and the request goes on verbatim with
		// the command nobody looked at still in it.
		$xml = '<?xml version="1.0"?><methodCall><methodName>d.multicall</methodName>'
			. '<params>'
			. '<param><value><string></string></value></param>'
			. '<param><value><string>main</string></value></param>'
			. '<param><value><base64>' . base64_encode('execute=/bin/id')
			. '</base64></value></param>'
			. '</params></methodCall>';
		$d = $this->decide($xml);

		$this->assertTrue($d['action'] === 'reject',
			'a base64-encoded execute parameter is refused');
		$this->assertTrue(isset($d['method']) && $d['method'] === 'execute',
			'the refusal names execute, the command the base64 decodes to');
	}

	public function testABase64AllowedParameterStillGetsThrough()
	{
		// The decoding must not turn an allowed command into a refusal.
		$xml = '<?xml version="1.0"?><methodCall><methodName>load.raw_start</methodName>'
			. '<params>'
			. '<param><value><string></string></value></param>'
			. '<param><value><base64>' . base64_encode('d1:ae') . '</base64></value></param>'
			. '<param><value><base64>' . base64_encode('d.custom1.set=label')
			. '</base64></value></param>'
			. '</params></methodCall>';
		$d = $this->decide($xml);

		$this->assertTrue($d['action'] === 'send',
			'a base64-encoded allowed command parameter is forwarded');
		$this->assertTrue(strpos($d['payload'], 'd.custom1.set="label"') !== false,
			'and it is rebuilt as the command it decodes to');
	}

	// --- a command nested inside another command's arguments ---

	public function testACommandNestedInAMulticallArgumentIsRefused()
	{
		// d.multicall runs the commands it is given after the view, so a
		// command parameter that is itself a multicall carries commands of its
		// own. Reading only the name before the first '=' answers for the
		// outer call and for nothing it carries.
		$xml = $this->call('d.multicall',
			array('', 'main', 'd.multicall=main,execute=/bin/id'));
		$d = $this->decide($xml);

		$this->assertTrue($d['action'] === 'reject',
			'a multicall parameter carrying a nested execute is refused');
		$this->assertTrue(isset($d['method']) && $d['method'] === 'execute',
			'the refusal names the nested execute');
	}

	public function testADollarIntroducedCommandIsRefused()
	{
		// '$' is what makes rtorrent call a name rather than read it as text,
		// and it can stand anywhere. '$execute' has no '=' before it, so the
		// name read off the front of the string was "$execute", which begins
		// with no refused prefix.
		$xml = $this->call('d.multicall', array('', 'main', '$execute=/bin/id'));
		$d = $this->decide($xml);

		$this->assertTrue($d['action'] === 'reject',
			'a $-introduced execute is refused');
		$this->assertTrue(isset($d['method']) && $d['method'] === 'execute',
			'the refusal names execute');
	}

	// --- a call wrapped in a system.multicall ---

	public function testSystemMulticallMemberParametersAreJudged()
	{
		// The member's own name is d.multicall, which nothing refuses. Its
		// parameters are where a multicall carries its commands, and they were
		// never looked at.
		$xml = '<?xml version="1.0"?><methodCall><methodName>system.multicall</methodName>'
			. '<params><param><value><array><data>'
			. '<value><struct>'
			. '<member><name>methodName</name><value><string>d.multicall</string></value></member>'
			. '<member><name>params</name><value><array><data>'
			. '<value><string></string></value>'
			. '<value><string>main</string></value>'
			. '<value><string>execute=/bin/id</string></value>'
			. '</data></array></value></member>'
			. '</struct></value>'
			. '</data></array></value></param></params></methodCall>';
		$d = $this->decide($xml);

		$this->assertTrue($d['action'] === 'reject',
			'a system.multicall member carrying execute is refused');
		$this->assertTrue(isset($d['method']) && $d['method'] === 'execute',
			'the refusal names execute, not the wrapper');
	}

	public function testSystemMulticallMemberNamesAreStillJudged()
	{
		// What the member-name check already covered has to keep working.
		$xml = '<?xml version="1.0"?><methodCall><methodName>system.multicall</methodName>'
			. '<params><param><value><array><data>'
			. '<value><struct>'
			. '<member><name>methodName</name><value><string>system.shutdown</string></value></member>'
			. '<member><name>params</name><value><array><data></data></array></value></member>'
			. '</struct></value>'
			. '</data></array></value></param></params></methodCall>';
		$d = $this->decide($xml);

		$this->assertTrue($d['action'] === 'reject',
			'a system.multicall member named system.shutdown is refused');
		$this->assertTrue(isset($d['method']) && $d['method'] === 'system.shutdown',
			'the refusal names system.shutdown');
	}

	public function testANestedSystemMulticallIsRefused()
	{
		$xml = '<?xml version="1.0"?><methodCall><methodName>system.multicall</methodName>'
			. '<params><param><value><array><data>'
			. '<value><struct>'
			. '<member><name>methodName</name><value><string>system.multicall</string></value></member>'
			. '<member><name>params</name><value><array><data></data></array></value></member>'
			. '</struct></value>'
			. '</data></array></value></param></params></methodCall>';
		$d = $this->decide($xml);

		$this->assertTrue($d['action'] === 'reject',
			'a system.multicall nested in a system.multicall is refused');
	}

	// --- what must keep working ---

	public function testAnOrdinaryReadMulticallIsStillForwarded()
	{
		$xml = $this->call('d.multicall', array('', 'main', 'd.name=', 'd.get_custom=seedingtime'));
		$d = $this->decide($xml);

		$this->assertTrue($d['action'] === 'send',
			'an ordinary read multicall is still forwarded');
	}

	public function testACustomFieldNamedAfterARefusedWordIsNotRefusedForIt()
	{
		// "scheduled" begins with the refused prefix "schedule", but it stands
		// where a command's argument stands, not where a command stands. What
		// follows '=' inside one element is argument text and is not a name.
		$xml = $this->call('d.multicall', array('', 'main', 'd.get_custom=scheduled'));
		$d = $this->decide($xml);

		$this->assertTrue($d['action'] === 'send',
			'a custom field named "scheduled" is not mistaken for schedule');
	}

	public function testTheShippedTrackerColumnExpressionIsNotRefused()
	{
		// plugins/show_peers_like_wtorrent ships this, nested and $-introduced.
		$expression = 'cat="$t.multicall=d.get_hash=,t.get_scrape_complete=,cat={#}"';
		$xml = $this->call('d.multicall', array('', 'main', $expression));
		$d = $this->decide($xml);

		$this->assertTrue($d['action'] === 'send',
			'the shipped nested tracker-column expression is still forwarded');
	}
}
