<?php

require_once(__DIR__ . '/TestCase.php');

class HttprpcTrustTest extends TestCase
{
    /**
     * Simulate the trust whitelist logic from httprpc/action.php default case.
     */
    private function isTrustedMethod($xmlData)
    {
        $trusted = false;
        $xml = @simplexml_load_string($xmlData);
        if ($xml !== false && isset($xml->methodName)) {
            $methodName = (string)$xml->methodName;
            $trustedMethods = array(
                'load.start', 'load.raw_start', 'load.raw', 'load.normal',
                'load_start', 'load_raw_start', 'load_raw',
            );
            $trusted = in_array($methodName, $trustedMethods);
        }
        return $trusted;
    }

    public function testLoadStartIsTrusted()
    {
        $xml = '<?xml version="1.0"?><methodCall><methodName>load.start</methodName><params><param><value><string>http://example.com/test.torrent</string></value></param></params></methodCall>';
        $this->assertTrue($this->isTrustedMethod($xml), 'load.start should be trusted');
    }

    public function testLoadRawStartIsTrusted()
    {
        $xml = '<?xml version="1.0"?><methodCall><methodName>load.raw_start</methodName><params><param><value><base64>...</base64></value></param></params></methodCall>';
        $this->assertTrue($this->isTrustedMethod($xml), 'load.raw_start should be trusted');
    }

    public function testLoadRawIsTrusted()
    {
        $xml = '<?xml version="1.0"?><methodCall><methodName>load.raw</methodName><params></params></methodCall>';
        $this->assertTrue($this->isTrustedMethod($xml), 'load.raw should be trusted');
    }

    public function testLegacyLoadStartIsTrusted()
    {
        $xml = '<?xml version="1.0"?><methodCall><methodName>load_start</methodName><params></params></methodCall>';
        $this->assertTrue($this->isTrustedMethod($xml), 'load_start (legacy) should be trusted');
    }

    public function testExecuteIsUntrusted()
    {
        $xml = '<?xml version="1.0"?><methodCall><methodName>execute</methodName><params><param><value><string>rm -rf /</string></value></param></params></methodCall>';
        $this->assertTrue(!$this->isTrustedMethod($xml), 'execute should be untrusted');
    }

    public function testSystemMulticallIsUntrusted()
    {
        $xml = '<?xml version="1.0"?><methodCall><methodName>system.multicall</methodName><params><param><value><array><data><value><struct><member><name>methodName</name><value><string>execute</string></value></member></struct></value></data></array></value></param></params></methodCall>';
        $this->assertTrue(!$this->isTrustedMethod($xml), 'system.multicall should be untrusted even with execute nested inside');
    }

    public function testInvalidXmlIsUntrusted()
    {
        $this->assertTrue(!$this->isTrustedMethod('not xml at all'), 'invalid XML should be untrusted');
    }

    public function testEmptyIsUntrusted()
    {
        $this->assertTrue(!$this->isTrustedMethod(''), 'empty input should be untrusted');
    }

    public function testMethodNameInParamsIsIgnored()
    {
        // Attempt to sneak load.start as a parameter value, not as the actual method
        $xml = '<?xml version="1.0"?><methodCall><methodName>execute</methodName><params><param><value><string>load.start</string></value></param></params></methodCall>';
        $this->assertTrue(!$this->isTrustedMethod($xml), 'load.start in params should not make execute trusted');
    }

    public function testDStartIsUntrusted()
    {
        $xml = '<?xml version="1.0"?><methodCall><methodName>d.start</methodName><params></params></methodCall>';
        $this->assertTrue(!$this->isTrustedMethod($xml), 'd.start should be untrusted in raw XMLRPC (has its own named handler)');
    }
}
