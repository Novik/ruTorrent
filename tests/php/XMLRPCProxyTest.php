<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/xmlrpc_proxy.php');

class XMLRPCProxyTest extends TestCase
{
    public function testOffModeRejectsAll()
    {
        $xml = '<?xml version="1.0"?><methodCall><methodName>system.client_version</methodName><params></params></methodCall>';
        $result = XMLRPCProxy::process($xml, 'off');
        $this->assertTrue($result === null, 'off mode should return null');
    }

    public function testSanitizeMethodsList()
    {
        $ref = new ReflectionProperty('XMLRPCProxy', 'sanitizeMethods');
        $ref->setAccessible(true);
        $methods = $ref->getValue();
        $this->assertTrue(in_array('load.start', $methods), 'load.start should be in sanitize list');
        $this->assertTrue(in_array('load.raw_start', $methods), 'load.raw_start should be in sanitize list');
        $this->assertTrue(!in_array('execute', $methods), 'execute should NOT be in sanitize list');
        $this->assertTrue(!in_array('system.multicall', $methods), 'system.multicall should NOT be in sanitize list');
        $this->assertTrue(!in_array('execute2', $methods), 'execute2 should NOT be in sanitize list');
    }

    public function testInvalidXmlParsesToFalse()
    {
        $xml = @simplexml_load_string('not xml');
        $this->assertTrue($xml === false, 'invalid XML should return false');
    }

    public function testValidXmlMethodExtraction()
    {
        $xml = simplexml_load_string('<?xml version="1.0"?><methodCall><methodName>load.start</methodName><params></params></methodCall>');
        $this->assertEquals('load.start', (string)$xml->methodName, 'should extract method name');
    }

    public function testParamCountPreservedForSafeLoad()
    {
        $xml = simplexml_load_string('<?xml version="1.0"?><methodCall><methodName>load.start</methodName><params><param><value><string></string></value></param><param><value><string>http://example.com/t.torrent</string></value></param><param><value><string>execute=evil</string></value></param></params></methodCall>');
        // Original has 3 params
        $this->assertEquals(3, count($xml->params->param), 'original should have 3 params');
        // Sanitizer should keep only 2 (tested via integration)
    }

    public function testMethodNameInParamsNotConfused()
    {
        $xml = simplexml_load_string('<?xml version="1.0"?><methodCall><methodName>execute</methodName><params><param><value><string>load.start</string></value></param></params></methodCall>');
        $methodName = (string)$xml->methodName;
        $this->assertEquals('execute', $methodName, 'should read actual methodName, not params');
    }
}
