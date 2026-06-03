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
		$this->assertTrue(strpos($result['xml'], 'd.directory.set=/srv/torrents') !== false, 'safe param survives');
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
}
