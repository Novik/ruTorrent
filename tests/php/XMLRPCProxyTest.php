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
		public static $sent = 0;
		public static function send($data, $trusted)
		{
			self::$lastPayload = $data;
			self::$lastTrusted = $trusted;
			self::$sent++;
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
		rXMLRPCRequest::$sent = 0;
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

	// ---- multicalls carry commands too ----

	private function multicall($params, $safeParams = array('d.custom1.set'))
	{
		$this->resetMocks();
		$xml = '<?xml version="1.0"?><methodCall><methodName>d.multicall2</methodName><params>';
		foreach($params as $param)
			$xml .= '<param><value><string>' . htmlspecialchars($param, ENT_NOQUOTES)
				. '</string></value></param>';
		$xml .= '</params></methodCall>';
		XMLRPCProxy::process($xml, 'sanitize', true, $safeParams);
		return $xml;
	}

	public function testMulticallCommandsAreRebuiltLikeLoadParams()
	{
		$this->multicall(array('', 'main', 'd.custom1.set=Movies (2024)'));
		$this->assertTrue(strpos((string) rXMLRPCRequest::$lastPayload,
			'd.custom1.set="Movies (2024)"') !== false,
			'an allowed command in a multicall is quoted the same way as in a load');
		$this->assertTrue(rXMLRPCRequest::$lastTrusted === true,
			'and a multicall of nothing but allowed commands may be trusted');
	}

	/**
	 * The one place multicalls differ from load.*: a load can lose a command
	 * and still add the torrent, but a multicall's commands are the request.
	 * Dropping one would answer with a short row and no fault, so the request
	 * goes on untouched and rtorrent's own gate decides.
	 */
	public function testMulticallWithAnUnknownCommandIsForwardedUntouched()
	{
		$sent = $this->multicall(array('', 'main', 'd.name='));
		$this->assertTrue(rXMLRPCRequest::$lastPayload === $sent,
			'the request is forwarded byte for byte, not rebuilt');
		$this->assertTrue(rXMLRPCRequest::$lastTrusted === false,
			'and untrusted, so rtorrent applies its own restrictions');
	}

	public function testMulticallNeverSilentlyDropsACommand()
	{
		$this->multicall(array('', 'main', 'd.custom1.set=label', 'd.name='));
		$this->assertTrue(strpos((string) rXMLRPCRequest::$lastPayload, 'd.name=') !== false,
			'a read command is not stripped out of the caller\'s multicall');
		$this->assertTrue(rXMLRPCRequest::$lastTrusted === false,
			'the mixed call goes untrusted rather than losing a command');
	}

	public function testMulticallCarryingExecuteIsRefused()
	{
		$this->multicall(array('', 'main', 'execute.capture=/bin/sh,-c,id'));
		$this->assertTrue(rXMLRPCRequest::$sent === 0,
			'a multicall carrying execute.capture is refused, not forwarded');
		$this->assertTrue(rXMLRPCRequest::$lastTrusted === null,
			'and it certainly is not trusted');
	}

	public function testMulticallDollarArgumentIsNeverTrusted()
	{
		$this->multicall(array('', 'main', 'd.custom1.set=$execute.capture=/bin/hostname'));
		$this->assertTrue(rXMLRPCRequest::$lastTrusted === false,
			'an allowed command whose argument would be re-parsed is not trusted');
		$this->assertTrue(strpos((string) rXMLRPCRequest::$lastPayload, 'execute.capture') !== false,
			'the original is forwarded rather than a rebuilt one, so nothing is smuggled in quoted');
	}

	public function testMulticallViewNameIsDataNotACommand()
	{
		$this->multicall(array('', 'd.custom1.set=notacommand', 'd.custom1.set=label'));
		$this->assertTrue(strpos((string) rXMLRPCRequest::$lastPayload,
			'<string>d.custom1.set=notacommand</string>') !== false,
			'the view name is re-emitted as the value it is, not quoted as a command');
	}

	public function testCommandCarryingMethodsList()
	{
		$ref = new ReflectionProperty('XMLRPCProxy', 'multicallMethods');
		$ref->setAccessible(true);
		$methods = $ref->getValue();
		$this->assertTrue(in_array('d.multicall2', $methods), 'd.multicall2 is command-carrying');
		$this->assertTrue(in_array('t.multicall', $methods), 't.multicall is command-carrying');
		$this->assertTrue(!in_array('system.multicall', $methods),
			'system.multicall is NOT: its members are calls, not command strings');
		$this->assertTrue(!in_array('load.start', $methods),
			'load.start belongs to the list that strips, not this one');
	}

	// ---- load.* may not name a path on rtorrent's own filesystem ----

	private function load($uri, $allowLocalPaths = false, $method = 'load.start')
	{
		$this->resetMocks();
		$xml = '<?xml version="1.0"?><methodCall><methodName>' . $method
			. '</methodName><params>'
			. '<param><value><string></string></value></param>'
			. '<param><value><string>' . htmlspecialchars($uri, ENT_NOQUOTES) . '</string></value></param>'
			. '</params></methodCall>';
		return XMLRPCProxy::process($xml, 'sanitize', true, array('d.custom1.set'), $allowLocalPaths);
	}

	public function testLoadFromALocalPathIsRejected()
	{
		$this->assertTrue($this->load('/srv/watch/x.torrent') === null,
			'a load naming a path on rtorrent\'s own filesystem is refused');
		$this->assertTrue(rXMLRPCRequest::$lastPayload === null,
			'and nothing is sent, not even untrusted');
	}

	/**
	 * Untrusted is not a refusal on rtorrent below 0.16.10 — the header is read
	 * and ignored — so this one cannot be left for rtorrent to sort out.
	 */
	public function testLocalPathIsRefusedRatherThanForwardedUntrusted()
	{
		$this->load('/srv/watch/x.torrent');
		$this->assertTrue(rXMLRPCRequest::$sent === 0, 'the request never reaches rtorrent');
		$this->assertTrue(strpos($this->logText(), 'local path') !== false,
			'and the refusal says why');
	}

	public function testNetworkAndMagnetUrisAreAccepted()
	{
		foreach(array('http://example.test/x.torrent', 'https://example.test/x.torrent',
			'ftp://example.test/x.torrent', 'magnet:?xt=urn:btih:abc') as $uri)
		{
			$this->load($uri);
			$this->assertTrue(rXMLRPCRequest::$sent === 1, $uri . ' is forwarded');
		}
	}

	/**
	 * rtorrent compares these with strncmp, so anything it would not recognise
	 * as a URI is a path to it, and has to be a path here too. Matching more
	 * loosely than rtorrent does is exactly the hole this closes.
	 */
	public function testSchemeMatchingIsAsStrictAsRtorrents()
	{
		foreach(array('HTTP://example.test/x.torrent', 'Magnet:?xt=urn:btih:abc',
			'magnet:xt=urn:btih:abc', ' http://example.test/x.torrent',
			'file:///srv/watch/x.torrent', 'watch/x.torrent', '~/x.torrent') as $uri)
		{
			$this->assertTrue($this->load($uri) === null, $uri . ' is treated as a local path');
		}
	}

	public function testBase64EncodingDoesNotHideALocalPath()
	{
		$this->resetMocks();
		$xml = '<?xml version="1.0"?><methodCall><methodName>load.start</methodName><params>'
			. '<param><value><string></string></value></param>'
			. '<param><value><base64>' . base64_encode('/srv/watch/x.torrent') . '</base64></value></param>'
			. '</params></methodCall>';
		$this->assertTrue(XMLRPCProxy::process($xml, 'sanitize', true, array(), false) === null,
			'a base64 parameter is read as the URI it decodes to');
	}

	public function testRawLoadIsUnaffected()
	{
		$this->resetMocks();
		$xml = '<?xml version="1.0"?><methodCall><methodName>load.raw_start</methodName><params>'
			. '<param><value><string></string></value></param>'
			. '<param><value><base64>' . base64_encode('d4:infoe') . '</base64></value></param>'
			. '</params></methodCall>';
		XMLRPCProxy::process($xml, 'sanitize', true, array(), false);
		$this->assertTrue(rXMLRPCRequest::$sent === 1,
			'load.raw_start carries the torrent itself, not a URI, so it is untouched');
	}

	public function testOperatorCanAllowLocalPaths()
	{
		$this->load('/srv/watch/x.torrent', true);
		$this->assertTrue(rXMLRPCRequest::$sent === 1,
			'the setting exists for automation that posts server-local paths');
	}

	public function testLoadUriListDoesNotCoverTheRawMethods()
	{
		$ref = new ReflectionProperty('XMLRPCProxy', 'uriLoadMethods');
		$ref->setAccessible(true);
		$methods = $ref->getValue();
		$this->assertTrue(in_array('load.start', $methods), 'load.start takes a URI');
		$this->assertTrue(!in_array('load.raw_start', $methods),
			'load.raw_start takes the torrent, so it is not checked');
	}

	// ---- refused outright, without asking rtorrent ----

	private function callMethod($method, $params = array(), $mode = 'sanitize')
	{
		$this->resetMocks();
		$xml = '<?xml version="1.0"?><methodCall><methodName>' . htmlspecialchars($method)
			. '</methodName><params>';
		foreach($params as $p)
			$xml .= '<param><value><string>' . htmlspecialchars($p, ENT_NOQUOTES) . '</string></value></param>';
		$xml .= '</params></methodCall>';
		return XMLRPCProxy::process($xml, $mode, true, array('d.custom1.set'));
	}

	public function testExecutionPrimitivesAreRefused()
	{
		foreach(array('execute', 'execute.capture', 'execute.raw.bg', 'execute2',
			'method.insert', 'method.set_key', 'import', 'try_import',
			'schedule', 'schedule2', 'schedule.remove', 'log.execute',
			'log.open_file', 'network.scgi.open_port', 'catch', 'system.env') as $method)
		{
			$this->assertTrue($this->callMethod($method) === null, $method . ' is refused');
			$this->assertTrue(rXMLRPCRequest::$sent === 0, $method . ' never reaches rtorrent');
		}
	}

	/**
	 * The families are spelled differently across versions — 0.9.8 has execute2
	 * and schedule_remove2, 0.16.x has execute.raw.bg and schedule.remove — so
	 * the list matches prefixes. An exact list would go stale silently, and for
	 * a refusal list that is the wrong way to fail.
	 */
	public function testRefusalMatchesTheWholeFamily()
	{
		$ref = new ReflectionProperty('XMLRPCProxy', 'denyPrefixes');
		$ref->setAccessible(true);
		$this->assertTrue(in_array('execute', $ref->getValue()),
			'one entry covers every execute spelling');
		$this->assertTrue($this->callMethod('execute.capture_nothrow') === null,
			'including the ones not written down anywhere');
	}

	public function testHarmlessMethodsAreNotCaughtByTheRefusalList()
	{
		foreach(array('system.client_version', 'd.name', 'view.list', 'directory.default') as $method)
		{
			$this->callMethod($method);
			$this->assertTrue(rXMLRPCRequest::$sent === 1, $method . ' is still forwarded');
			$this->assertTrue(rXMLRPCRequest::$lastTrusted === false, $method . ' untrusted');
		}
	}

	public function testPassthroughUnsafeIsNotSubjectToTheRefusalList()
	{
		$this->callMethod('execute.capture', array('', '/bin/sh'), 'passthrough_unsafe');
		$this->assertTrue(rXMLRPCRequest::$sent === 1,
			'passthrough_unsafe is documented as dangerous and stays literal');
		$this->assertTrue(rXMLRPCRequest::$lastTrusted === true, 'and trusted');
	}

	public function testSystemMulticallMembersAreJudgedToo()
	{
		$this->resetMocks();
		$xml = '<?xml version="1.0"?><methodCall><methodName>system.multicall</methodName>'
			. '<params><param><value><array><data><value><struct>'
			. '<member><name>methodName</name><value><string>execute.capture</string></value></member>'
			. '<member><name>params</name><value><array><data>'
			. '<value><string></string></value></data></array></value></member>'
			. '</struct></value></data></array></value></param></params></methodCall>';
		$this->assertTrue(XMLRPCProxy::process($xml, 'sanitize', true, array()) === null,
			'a refused method does not get through inside a struct');
		$this->assertTrue(rXMLRPCRequest::$sent === 0, 'and nothing is forwarded');
	}

	// ---- elevated: refused by rtorrent untrusted, needed by real clients ----

	public function testOneDownloadByHashIsElevated()
	{
		foreach(array('d.open', 'd.start', 'd.stop', 'd.delete_tied') as $method)
		{
			$this->callMethod($method, array('0123456789abcdef0123456789ABCDEF01234567'));
			$this->assertTrue(rXMLRPCRequest::$lastTrusted === true, $method . ' is elevated');
			$this->assertTrue(strpos((string) rXMLRPCRequest::$lastPayload,
				'0123456789ABCDEF0123456789ABCDEF01234567') !== false,
				$method . ' is re-emitted with the hash this side validated');
		}
	}

	public function testAnArgumentThatIsNotAHashIsNotElevated()
	{
		foreach(array('not-a-hash', '', '0123456789abcdef0123456789ABCDEF0123456',
			'0123456789abcdef0123456789ABCDEF012345678', '../../etc/passwd') as $bad)
		{
			$this->callMethod('d.start', array($bad));
			$this->assertTrue(rXMLRPCRequest::$lastTrusted === false,
				var_export($bad, true) . ' is not a hash, so the call is left untrusted');
		}
	}

	public function testTheArgumentCountHasToMatch()
	{
		$this->callMethod('d.start', array('0123456789ABCDEF0123456789ABCDEF01234567', 'extra'));
		$this->assertTrue(rXMLRPCRequest::$lastTrusted === false,
			'an extra argument means the call is not the shape that was approved');
	}

	public function testAnElevatedValueIsCarriedAsDataNotAsACommand()
	{
		$this->callMethod('d.custom1.set',
			array('0123456789ABCDEF0123456789ABCDEF01234567', '$execute.capture=/bin/hostname'));
		$this->assertTrue(rXMLRPCRequest::$lastTrusted === true, 'the call is elevated');
		$this->assertTrue(strpos((string) rXMLRPCRequest::$lastPayload,
			'<string>$execute.capture=/bin/hostname</string>') !== false,
			'and the value travels as a string parameter, which rtorrent stores rather than parses');
	}

	public function testTheSizeLimitIsClamped()
	{
		$this->callMethod('network.xmlrpc.size_limit.set', array('', '999999999'));
		$this->assertTrue(rXMLRPCRequest::$lastTrusted === true, 'the call is elevated');
		$this->assertTrue(strpos((string) rXMLRPCRequest::$lastPayload, '<i8>16777216</i8>') !== false,
			'a client raising it to add a big torrent is fine; an unbounded value is not');
		$this->callMethod('network.xmlrpc.size_limit.set', array('', '2097152'));
		$this->assertTrue(strpos((string) rXMLRPCRequest::$lastPayload, '<i8>2097152</i8>') !== false,
			'a value under the ceiling is passed through as asked');
	}

	public function testElevationListHoldsNoCommandCarryingMethod()
	{
		$ref = new ReflectionProperty('XMLRPCProxy', 'elevate');
		$ref->setAccessible(true);
		$elevated = array_keys($ref->getValue());
		foreach(array('load.start', 'load.raw_start', 'd.multicall2', 't.multicall') as $method)
			$this->assertTrue(!in_array($method, $elevated),
				$method . ' takes command strings, so it is rebuilt rather than elevated');
	}

	// ---- where a download may be written ----
	//
	// d.directory.set names the directory rtorrent writes a download into, and
	// the caller supplies the torrent, so it names the file too. Unconfined and
	// forwarded trusted, that is an arbitrary file write as the rtorrent user —
	// found by a tester writing a .php into a webroot and running it.

	private function loadInto($dir, $policy = null, $command = 'd.directory.set')
	{
		$this->resetMocks();
		$xml = '<?xml version="1.0"?><methodCall><methodName>load.start</methodName><params>'
			. '<param><value><string></string></value></param>'
			. '<param><value><string>http://example.test/x.torrent</string></value></param>'
			. '<param><value><string>' . $command . '=' . htmlspecialchars($dir, ENT_NOQUOTES)
			. '</string></value></param>'
			. '</params></methodCall>';
		$options = ($policy === null) ? array() : array('directory' => $policy);
		XMLRPCProxy::process($xml, 'sanitize', true,
			array('d.directory.set', 'd.directory_base.set'), false, $options);
		return strpos((string) rXMLRPCRequest::$lastPayload, $command . '=') !== false;
	}

	public function testADirectoryOutsideTheBoundaryIsDropped()
	{
		$policy = array('root' => '/torrents1/downloads');
		$this->assertTrue(!$this->loadInto('/var/www/user1/rtorrent/share/settings/x/', $policy),
			'the reported attack — a webroot path — does not reach rtorrent');
		$this->assertTrue(rXMLRPCRequest::$sent === 1,
			'and the torrent is still added, to the default directory');
	}

	public function testADirectoryInsideTheBoundaryIsKept()
	{
		$policy = array('root' => '/torrents1/downloads');
		$this->assertTrue($this->loadInto('/torrents1/downloads/Movies', $policy),
			'a directory the customer is entitled to still works');
		$this->assertTrue($this->loadInto('/torrents1/downloads', $policy),
			'the boundary itself is inside it');
	}

	public function testDirectoryBaseIsConfinedTheSameWay()
	{
		$policy = array('root' => '/torrents1/downloads');
		$this->assertTrue(!$this->loadInto('/var/www/user1', $policy, 'd.directory_base.set'),
			'd.directory_base.set sets the root directly, so it is the blunter of the two');
		$this->assertTrue($this->loadInto('/torrents1/downloads/x', $policy, 'd.directory_base.set'),
			'and still works inside the boundary');
	}

	public function testPathTricksDoNotEscape()
	{
		$policy = array('root' => '/torrents1/downloads');
		foreach(array(
			'/torrents1/downloads/../../var/www',   // climbing out
			'/torrents1/downloads/./../../etc',     // with a . in the way
			'/torrents1/downloadsEVIL',             // a prefix, not a child
			'/torrents1/downloads/../downloads2',   // sibling
			'downloads/x',                          // not absolute at all
			'',                                     // nothing
		) as $dir)
		{
			$this->assertTrue(!$this->loadInto($dir, $policy),
				var_export($dir, true) . ' is not inside the boundary');
		}
	}

	/**
	 * The value normally does not exist yet, so realpath() on it answers
	 * nothing and a lexical check is all that is left — which one symlink
	 * inside the tree defeats, and the customer can create symlinks. The
	 * resolver is asked about the deepest part that does exist.
	 */
	public function testASymlinkOutOfTheTreeIsCaught()
	{
		$policy = array(
			'root' => '/torrents1/downloads',
			'resolve' => function($path) {
				// stands in for a symlink at /torrents1/downloads/escape
				if(strpos($path, '/torrents1/downloads/escape') === 0)
					return '/var/www/user1' . substr($path, strlen('/torrents1/downloads/escape'));
				return $path;
			},
		);
		$this->assertTrue(!$this->loadInto('/torrents1/downloads/escape/x', $policy),
			'a path that is inside on paper and outside in fact is dropped');
		$this->assertTrue($this->loadInto('/torrents1/downloads/real/x', $policy),
			'and one that resolves where it says still works');
	}

	public function testAResolverThatCannotAnswerIsANo()
	{
		$policy = array(
			'root' => '/torrents1/downloads',
			'resolve' => function($path) { return ''; },
		);
		$this->assertTrue(!$this->loadInto('/torrents1/downloads/x', $policy),
			'an open question about a write target is not a yes');
	}

	public function testNoBoundaryStatedMeansNoBoundaryChecked()
	{
		// Every caller behaved this way before the check existed, and the
		// httprpc plugin still does — its door needs a ruTorrent session, not a
		// machine credential. rpc2.php always states a boundary and refuses to
		// start without one.
		$this->assertTrue($this->loadInto('/anywhere/at/all', null),
			'a caller that states no boundary is not policed here');
	}

	public function testAPolicyWithNoRootRefuses()
	{
		$this->assertTrue(!$this->loadInto('/torrents1/downloads/x', array()),
			'a stated policy that names no root permits nothing, rather than everything');
	}

	public function testTheConfinedCommandsAreTheOnesThatWriteSomewhere()
	{
		$ref = new ReflectionProperty('XMLRPCProxy', 'directoryCommands');
		$ref->setAccessible(true);
		$commands = $ref->getValue();
		$this->assertTrue(in_array('d.directory.set', $commands), 'd.directory.set is confined');
		$this->assertTrue(in_array('d.directory_base.set', $commands), 'd.directory_base.set is confined');
		$this->assertTrue(!in_array('d.custom1.set', $commands),
			'a label is not a path and is not confined');
	}

	public function testSystemMulticallIsStillForwardedUntouched()
	{
		$this->resetMocks();
		$xml = '<?xml version="1.0"?><methodCall><methodName>system.multicall</methodName>'
			. '<params><param><value><string>x</string></value></param></params></methodCall>';
		XMLRPCProxy::process($xml, 'sanitize', true, array('d.custom1.set'));
		$this->assertTrue(rXMLRPCRequest::$lastPayload === $xml, 'forwarded byte for byte');
		$this->assertTrue(rXMLRPCRequest::$lastTrusted === false, 'and untrusted');
	}
}
