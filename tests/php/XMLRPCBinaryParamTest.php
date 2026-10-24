<?php

require_once(__DIR__.'/TestCase.php');
require_once(__DIR__.'/PhpProcess.php');
require_once(__DIR__.'/../../php/xmlrpc.php');

class XMLRPCBinaryParamProbe extends rXMLRPCRequest
{
	public $built = false;
	protected function makeNextCall() { $this->built = true; return false; }
}

class XMLRPCBinaryParamTest extends TestCase
{
	private function assertSame($expected, $actual, $message = null)
	{
		$this->assertTrue($expected === $actual, $message);
	}

	public function testLegacyPathsAndXmlNormalizedBytesUseBinaryParameters()
	{
		foreach(array("/data/\xF1\xE5\xF0\xE8\xFF", "/data/a\rb", "control\x01", "zero\0byte", "\xEF\xBF\xBE", "\xEF\xBF\xBF") as $raw)
		{
			$parameter = new rXMLRPCParam('string', $raw);
			$this->assertSame('base64', $parameter->type, 'XML cannot change or discard path bytes');
			$this->assertSame($raw, base64_decode($parameter->value, true), 'the native string argument retains every byte');
		}
	}

	public function testOrdinaryUnicodeAndExplicitBinaryKeepTheirWireFormat()
	{
		foreach(array('', "русский/中文🙂", "a\tb\nc", "a'\"\\%2F<&") as $raw)
		{
			$parameter = new rXMLRPCParam('string', $raw);
			$this->assertSame('string', $parameter->type);
			$this->assertSame(htmlspecialchars($raw, ENT_NOQUOTES, 'UTF-8'), $parameter->value);
		}
		$encoded = base64_encode("raw\xFF");
		$parameter = new rXMLRPCParam('base64', $encoded);
		$this->assertSame($encoded, $parameter->value, 'explicit load.raw metainfo is not encoded twice');
	}

	public function testMissingBinaryFunctionsCannotBecomeAnEmptyArgument()
	{
		$source = file_get_contents(__DIR__.'/../../php/xmlrpc.php');
		$begin = strpos($source, 'class rXMLRPCParam');
		$end = strpos($source, "\nclass rXMLRPCInvalidCommandName");
		$definition = substr($source, $begin, $end-$begin);
		foreach(array('base64_encode', 'base64_decode', 'preg_match') as $disabled)
		{
			$code = 'require '.var_export(__DIR__.'/../../php/utility/utf.php', true).';'.$definition
				.'$p=new rXMLRPCParam("string","raw\\xFF");echo json_encode(array($p->type,$p->value));';
			$result = PhpProcess::run(array_merge(PhpProcess::sameIni(), array('-d', 'display_errors=1', '-d',
				'disable_functions='.$disabled, '-r', $code)));
			$this->assertSame(0, $result['code'], 'disabled converter does not throw through request construction');
			$this->assertSame('', $result['err'], 'disabled converter does not emit warnings');
			$this->assertSame(array('base64', $disabled === 'preg_match' ? base64_encode("raw\xFF") : null),
				json_decode($result['out'], true), 'an unavailable conversion never becomes an empty argument');
		}
	}

	public function testUnavailableValueRefusesTheWholeBatchBeforeDispatch()
	{
		$command = (new ReflectionClass('rXMLRPCCommand'))->newInstanceWithoutConstructor();
		$command->command = 'd.directory.set';
		$parameter = new rXMLRPCParam('base64', '');
		$parameter->value = null;
		$command->params = array($parameter);
		// The refused case must not depend on a cached or reachable daemon.
		$settingsClass = new ReflectionClass('rTorrentSettings');
		$settingsProperty = $settingsClass->getProperty('theSettings');
		if(PHP_VERSION_ID < 80100) $settingsProperty->setAccessible(true);
		$previous = $settingsProperty->getValue();
		$settingsProperty->setValue(null, $settingsClass->newInstanceWithoutConstructor());
		try
		{
			$request = new XMLRPCBinaryParamProbe($command);
			$this->assertSame(false, $request->run());
			$this->assertSame(true, $request->fault);
			$this->assertSame(false, $request->built, 'no earlier command can run with a missing later argument');
			$this->assertSame('Refused: could not encode an XMLRPC parameter.', $request->faultString);
		}
		finally
		{
			$settingsProperty->setValue(null, $previous);
		}
	}
}
