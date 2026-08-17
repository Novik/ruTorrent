<?php

require_once(__DIR__ . '/TestCase.php');

// Stub the dependencies that production callers (httprpc/action.php) load
// before invoking XMLRPCProxy. We don't exercise the real SCGI path here —
// we verify XMLRPCProxy's own logic.
if(!class_exists('FileUtil'))
{
	class FileUtil
	{
		public static $log = array();
		public static function toLog($msg) { self::$log[] = $msg; }
	}
}
if(!class_exists('rXMLRPCRequest'))
{
	class rXMLRPCRequest
	{
		public static $lastPayload = null;
		public static $lastTrusted = null;
		public static function send($data, $trusted)
		{
			self::$lastPayload = $data;
			self::$lastTrusted = $trusted;
			return '';
		}
	}
}

require_once(__DIR__ . '/../../php/xmlrpc_proxy.php');

class XMLRPCProxyTest extends TestCase
{
	private function resetMocks()
	{
		rXMLRPCRequest::$lastPayload = null;
		rXMLRPCRequest::$lastTrusted = null;
		FileUtil::$log = array();
	}

	// ---- Mode dispatch ----

	public function testOffModeReturnsNull()
	{
		$this->resetMocks();
		$xml = '<?xml version="1.0"?><methodCall><methodName>load.start</methodName><params></params></methodCall>';
		$this->assertTrue(XMLRPCProxy::process($xml, 'off') === null, 'off mode returns null');
	}

	public function testOffModeRejectsGarbage()
	{
		$this->resetMocks();
		$this->assertTrue(XMLRPCProxy::process('not xml at all', 'off') === null, 'off mode rejects garbage too');
	}

	public function testPassthroughUnsafeForwardsTrusted()
	{
		$this->resetMocks();
		$xml = '<?xml version="1.0"?><methodCall><methodName>execute</methodName><params></params></methodCall>';
		XMLRPCProxy::process($xml, 'passthrough_unsafe');
		$this->assertTrue(rXMLRPCRequest::$lastTrusted === true, 'passthrough_unsafe forwards as trusted');
		$this->assertEquals($xml, rXMLRPCRequest::$lastPayload, 'passthrough_unsafe forwards payload verbatim');
	}

	public function testInvalidXmlForwardsUntrusted()
	{
		$this->resetMocks();
		XMLRPCProxy::process('not xml at all', 'sanitize');
		$this->assertTrue(rXMLRPCRequest::$lastTrusted === false, 'invalid XML forwards as untrusted');
	}

	public function testNonLoadMethodForwardsUntrusted()
	{
		$this->resetMocks();
		$xml = '<?xml version="1.0"?><methodCall><methodName>system.client_version</methodName><params></params></methodCall>';
		XMLRPCProxy::process($xml, 'sanitize');
		$this->assertTrue(rXMLRPCRequest::$lastTrusted === false, 'non-load method forwarded as untrusted');
	}

	// ---- Sanitize-mode whitelist (the security-critical path) ----

	public function testSanitizeStripsDangerousCommandParam()
	{
		$xml = simplexml_load_string('<?xml version="1.0"?><methodCall><methodName>load.start</methodName><params><param><value><string></string></value></param><param><value><string>http://example.com/t.torrent</string></value></param><param><value><string>execute=evil</string></value></param></params></methodCall>');
		$result = XMLRPCProxy::rebuildLoadParams($xml, 'load.start', array('d.directory.set', 'd.custom1.set'));
		$this->assertEquals(2, $result['kept'], 'should keep target + URL only');
		$this->assertEquals(1, count($result['stripped']), 'should strip one param');
		$this->assertTrue(strpos($result['xml'], 'execute=evil') === false, 'rebuilt XML must not contain execute=evil');
	}

	public function testSanitizeKeepsWhitelistedCommandParam()
	{
		$xml = simplexml_load_string('<?xml version="1.0"?><methodCall><methodName>load.start</methodName><params><param><value><string></string></value></param><param><value><string>http://example.com/t.torrent</string></value></param><param><value><string>d.directory.set=/srv/torrents</string></value></param></params></methodCall>');
		$result = XMLRPCProxy::rebuildLoadParams($xml, 'load.start', array('d.directory.set', 'd.custom1.set'));
		$this->assertEquals(3, $result['kept'], 'should keep target + URL + safe param');
		$this->assertEquals(0, count($result['stripped']), 'should strip nothing');
		$this->assertTrue(strpos($result['xml'], 'd.directory.set="/srv/torrents"') !== false,
			'safe param survives, rebuilt as a quoted argument');
	}

	public function testSanitizeAlwaysKeepsFirstTwoParams()
	{
		$xml = simplexml_load_string('<?xml version="1.0"?><methodCall><methodName>load.start</methodName><params><param><value><string></string></value></param><param><value><string>execute=looks_evil_but_is_url</string></value></param></params></methodCall>');
		$result = XMLRPCProxy::rebuildLoadParams($xml, 'load.start', array());
		$this->assertEquals(2, $result['kept'], 'positional params always kept');
	}

	public function testEmptyWhitelistStripsAllCommandParams()
	{
		$xml = simplexml_load_string('<?xml version="1.0"?><methodCall><methodName>load.start</methodName><params><param><value><string></string></value></param><param><value><string>http://example.com/t.torrent</string></value></param><param><value><string>d.directory.set=/srv</string></value></param><param><value><string>d.custom1.set=label</string></value></param></params></methodCall>');
		$result = XMLRPCProxy::rebuildLoadParams($xml, 'load.start', array());
		$this->assertEquals(2, $result['kept'], 'empty whitelist keeps only positional');
		$this->assertEquals(2, count($result['stripped']), 'both command params stripped');
	}

	public function testRebuiltXmlIsValid()
	{
		$xml = simplexml_load_string('<?xml version="1.0"?><methodCall><methodName>load.start</methodName><params><param><value><string></string></value></param><param><value><string>http://example.com/t.torrent</string></value></param></params></methodCall>');
		$result = XMLRPCProxy::rebuildLoadParams($xml, 'load.start', array());
		$reparsed = @simplexml_load_string($result['xml']);
		$this->assertTrue($reparsed !== false, 'rebuilt XML round-trips through simplexml');
		$this->assertEquals('load.start', (string)$reparsed->methodName, 'method name preserved');
	}

	public function testSanitizeEndToEndForwardsCleanedPayload()
	{
		$this->resetMocks();
		$xml = '<?xml version="1.0"?><methodCall><methodName>load.start</methodName><params><param><value><string></string></value></param><param><value><string>http://example.com/t.torrent</string></value></param><param><value><string>execute=evil</string></value></param></params></methodCall>';
		XMLRPCProxy::process($xml, 'sanitize', false, array('d.directory.set'));
		$this->assertTrue(rXMLRPCRequest::$lastTrusted === true, 'sanitized load.start forwarded as trusted');
		$this->assertTrue(strpos(rXMLRPCRequest::$lastPayload, 'execute=evil') === false, 'forwarded payload must not contain malicious param');
		$this->assertTrue(strpos(rXMLRPCRequest::$lastPayload, 'http://example.com/t.torrent') !== false, 'URL preserved');
	}

	// ---- Sanity ----

	public function testSanitizeMethodsList()
	{
		$ref = new ReflectionProperty('XMLRPCProxy', 'sanitizeMethods');
		$ref->setAccessible(true);
		$methods = $ref->getValue();
		$this->assertTrue(in_array('load.start', $methods), 'load.start in sanitize list');
		$this->assertTrue(in_array('load.raw_start', $methods), 'load.raw_start in sanitize list');
		$this->assertTrue(!in_array('execute', $methods), 'execute NOT in sanitize list');
		$this->assertTrue(!in_array('system.multicall', $methods), 'system.multicall NOT in sanitize list');
		$this->assertTrue(!in_array('execute2', $methods), 'execute2 NOT in sanitize list');
	}

	// ---- A command parameter is not one command ----

	private function sanitizeParam($param, $safeParams = array('d.custom1.set', 'd.custom2.set', 'd.custom.set'))
	{
		$this->resetMocks();
		$xml = '<?xml version="1.0"?><methodCall><methodName>load.raw_start</methodName><params>'
			. '<param><value><string></string></value></param>'
			. '<param><value><string>http://example.test/x.torrent</string></value></param>'
			. '<param><value><string>' . htmlspecialchars($param, ENT_NOQUOTES) . '</string></value></param>'
			. '</params></methodCall>';
		XMLRPCProxy::process($xml, 'sanitize', false, $safeParams);
		return (string) rXMLRPCRequest::$lastPayload;
	}

	public function testChainedCommandIsNotForwarded()
	{
		$sent = $this->sanitizeParam('d.custom1.set=A;d.custom2.set=(execute.capture,/bin/sh,-c,id)');
		// The ';' and everything after it end up inside the first argument.
		$this->assertTrue(strpos($sent, 'd.custom1.set="A;d.custom2.set=(execute.capture"') !== false,
			'a chained command is forwarded as text inside an argument, not as a command');
	}

	public function testNestedCommandValueIsQuoted()
	{
		$sent = $this->sanitizeParam('d.custom2.set=(execute.capture,/bin/sh,-c,id)');
		$this->assertTrue(strpos($sent, 'd.custom2.set="(execute.capture"') !== false,
			'a parenthesised value must be forwarded quoted, as an argument');
	}

	public function testCommandNameMustMatchExactly()
	{
		$sent = $this->sanitizeParam('d.custom1.setEVIL=x;d.custom2.set=(execute.capture,/bin/sh,-c,"id")');
		$this->assertTrue(strpos($sent, 'custom1.setEVIL') === false, 'a command that merely starts with an allowed name is dropped');
		$this->assertTrue(strpos($sent, 'execute.capture') === false, 'and its payload goes with it');
	}

	public function testParameterWithoutSeparatorIsDropped()
	{
		$sent = $this->sanitizeParam('d.custom1.set');
		$this->assertTrue(strpos($sent, 'd.custom1.set') === false, 'a parameter with no = is dropped');
	}

	// ---- and the values clients legitimately send still arrive ----

	public function testValueKeepsCharactersThatUsedToBreakIt()
	{
		$sent = $this->sanitizeParam('d.custom1.set=Movies (2024)');
		$this->assertTrue(strpos($sent, 'd.custom1.set="Movies (2024)"') !== false,
			'parentheses and spaces survive as a quoted argument');
	}

	public function testQuotesAndBackslashesAreEscaped()
	{
		$sent = $this->sanitizeParam('d.custom1.set=say "hi" \\ bye');
		$this->assertTrue(strpos($sent, '\\"hi\\"') !== false, 'a quote in the value is escaped, not closing the argument');
	}

	public function testMultipleArgumentsArePreserved()
	{
		$sent = $this->sanitizeParam('d.custom.set=chk-state,7');
		$this->assertTrue(strpos($sent, 'd.custom.set="chk-state","7"') !== false,
			'a command taking two arguments still gets two');
	}

	// ---- trust ----

	public function testRebuiltRequestIsTrusted()
	{
		$this->sanitizeParam('d.custom1.set=label');
		$this->assertTrue(rXMLRPCRequest::$lastTrusted === true, 'a fully rebuilt request may be trusted');
	}

	public function testRequestIsUntrustedWhenAParamCannotBeRebuilt()
	{
		$this->resetMocks();
		// An <int> target is a type this side does not rebuild.
		$xml = '<?xml version="1.0"?><methodCall><methodName>load.raw_start</methodName><params>'
			. '<param><value><int>1</int></value></param>'
			. '<param><value><string>http://example.test/x.torrent</string></value></param>'
			. '</params></methodCall>';
		XMLRPCProxy::process($xml, 'sanitize', false, array('d.custom1.set'));
		$this->assertTrue(rXMLRPCRequest::$lastTrusted === false,
			'anything not rebuilt here is forwarded untrusted, for rtorrent to judge');
	}

	public function testDollarPrefixedArgumentIsDropped()
	{
		$sent = $this->sanitizeParam('d.custom1.set=$execute.capture=/bin/hostname');
		$this->assertTrue(strpos($sent, 'execute.capture') === false,
			'an argument that would be re-parsed as a command is dropped, not quoted');
	}

	public function testDollarPrefixedSecondArgumentIsDropped()
	{
		$sent = $this->sanitizeParam('d.custom.set=key,$execute.capture=/bin/hostname');
		$this->assertTrue(strpos($sent, 'execute.capture') === false,
			'every argument is checked, not only the first');
	}

	public function testDollarInsideAValueIsKept()
	{
		$sent = $this->sanitizeParam('d.custom1.set=cost 20$ or so');
		$this->assertTrue(strpos($sent, 'd.custom1.set="cost 20$ or so"') !== false,
			'only a leading $ is special, so ordinary values keep theirs');
	}

	// ---- what the log says ----

	private function logText()
	{
		return implode("\n", FileUtil::$log);
	}

	public function testUntrustedRequestIsNeverLoggedAsTrusted()
	{
		$this->resetMocks();
		// An <int> target is a type this side does not rebuild, so nothing is
		// stripped but the call still cannot be trusted.
		$xml = '<?xml version="1.0"?><methodCall><methodName>load.raw_start</methodName><params>'
			. '<param><value><int>1</int></value></param>'
			. '<param><value><string>http://example.test/x.torrent</string></value></param>'
			. '</params></methodCall>';
		XMLRPCProxy::process($xml, 'sanitize', true, array('d.custom1.set'));

		$this->assertTrue(rXMLRPCRequest::$lastTrusted === false, 'the call is sent untrusted');
		foreach(FileUtil::$log as $line)
			$this->assertTrue(strpos($line, 'xmlrpc-proxy: trusted') !== 0,
				'a request sent untrusted is never logged as trusted');
		$this->assertTrue(strpos($this->logText(), 'untrusted') !== false, 'and it is logged as untrusted');
	}

	public function testStrippedValueCannotForgeALogLine()
	{
		$this->resetMocks();
		$this->sanitizeParamLogged("execute=evil\nxmlrpc-proxy: trusted: load.raw_start (2 params)");
		$this->assertTrue(strpos($this->logText(), "\nxmlrpc-proxy: trusted") === false,
			'a newline in a stripped value cannot start a new log entry');
	}

	public function testLoggedValueIsLengthCapped()
	{
		$this->resetMocks();
		$this->sanitizeParamLogged('execute=' . str_repeat('A', 500));
		$this->assertTrue(strlen($this->logText()) < 400, 'a long stripped value is truncated');
	}

	private function sanitizeParamLogged($param)
	{
		$xml = '<?xml version="1.0"?><methodCall><methodName>load.raw_start</methodName><params>'
			. '<param><value><string></string></value></param>'
			. '<param><value><string>http://example.test/x.torrent</string></value></param>'
			. '<param><value><string>' . htmlspecialchars($param, ENT_NOQUOTES) . '</string></value></param>'
			. '</params></methodCall>';
		XMLRPCProxy::process($xml, 'sanitize', true, array('d.custom1.set'));
	}

	// ---- shapes rtorrent itself accepts ----

	public function testWhitespaceAroundTheCommandNameIsAccepted()
	{
		$this->assertTrue(strpos($this->sanitizeParam(' d.custom1.set=x'), 'd.custom1.set="x"') !== false,
			'a leading space does not hide the command name');
		$this->assertTrue(strpos($this->sanitizeParam('d.custom1.set =x'), 'd.custom1.set="x"') !== false,
			'nor does a space before the =');
	}

	public function testArgumentsAreTrimmedAsRtorrentTrimsThem()
	{
		$this->assertTrue(strpos($this->sanitizeParam('d.custom.set=chk-state, 7'), 'd.custom.set="chk-state","7"') !== false,
			'an unquoted argument is trimmed, matching what rtorrent stores');
	}

	public function testDollarIsCheckedAfterTrimming()
	{
		$sent = $this->sanitizeParam('d.custom1.set= $execute.capture=/bin/hostname');
		$this->assertTrue(strpos($sent, 'execute.capture') === false,
			'trimming must not quote a leading space into a leading $');
	}

	// ---- parameter forms ----

	public function testImplicitStringParamFormIsRead()
	{
		$this->resetMocks();
		$xml = '<?xml version="1.0"?><methodCall><methodName>load.raw_start</methodName><params>'
			. '<param><value><string></string></value></param>'
			. '<param><value><string>http://example.test/x.torrent</string></value></param>'
			. '<param><value>d.custom1.set=label</value></param>'
			. '</params></methodCall>';
		XMLRPCProxy::process($xml, 'sanitize', false, array('d.custom1.set'));
		$this->assertTrue(strpos((string) rXMLRPCRequest::$lastPayload, 'd.custom1.set="label"') !== false,
			'a value without an explicit <string> is read the same way');
	}

	public function testBase64DataParamIsRebuiltAndStillTrusted()
	{
		$this->resetMocks();
		$data = str_repeat("torrent-bytes\x00\xc8", 20);
		$xml = '<?xml version="1.0"?><methodCall><methodName>load.raw_start</methodName><params>'
			. '<param><value><string></string></value></param>'
			. '<param><value><base64>' . chunk_split(base64_encode($data), 76, "\n") . '</base64></value></param>'
			. '<param><value><string>d.custom1.set=label</string></value></param>'
			. '</params></methodCall>';
		XMLRPCProxy::process($xml, 'sanitize', false, array('d.custom1.set'));
		$sent = (string) rXMLRPCRequest::$lastPayload;

		$this->assertTrue(preg_match('#<base64>(.*?)</base64>#s', $sent, $m) === 1, 'the data param is still base64');
		$this->assertTrue(base64_decode($m[1], true) === $data, 'and it carries the same bytes, wrapping removed');
		$this->assertTrue(rXMLRPCRequest::$lastTrusted === true, 'a rebuilt base64 param does not force the call untrusted');
	}

	public function testPreQuotedValueIsDroppedNotMangled()
	{
		$this->resetMocks();
		$this->sanitizeParamLogged('d.custom1.set="Movies, Inc"');
		$this->assertTrue(strpos((string) rXMLRPCRequest::$lastPayload, 'd.custom1.set') === false,
			'a value the client quoted itself is dropped, not split inside its quotes');
		$this->assertTrue(strpos($this->logText(), 'stripped') !== false,
			'and the drop is visible in the log');
	}

	public function testUnknownMethodNameCannotForgeALogLine()
	{
		$this->resetMocks();
		$name = "system.foo\nxmlrpc-proxy: trusted: forged";
		$xml = '<?xml version="1.0"?><methodCall><methodName>' . htmlspecialchars($name)
			. '</methodName><params></params></methodCall>';
		XMLRPCProxy::process($xml, 'sanitize', true, array());

		$this->assertTrue(rXMLRPCRequest::$lastTrusted === false, 'an unknown method is sent untrusted');
		$this->assertTrue(strpos($this->logText(), "\nxmlrpc-proxy: trusted") === false,
			'a method name cannot start a log line of its own');
	}
}
