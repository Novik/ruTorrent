<?php

require_once(__DIR__ . '/TestCase.php');

class ConfigTest extends TestCase
{
	private $hadProfileMask;
	private $oldProfileMask;

	public function setUp()
	{
		$this->hadProfileMask = array_key_exists('RU_PROFILE_MASK', $_ENV);
		$this->oldProfileMask = $_ENV['RU_PROFILE_MASK'] ?? null;
	}

	public function tearDown()
	{
		if ($this->hadProfileMask) {
			$_ENV['RU_PROFILE_MASK'] = $this->oldProfileMask;
		} else {
			unset($_ENV['RU_PROFILE_MASK']);
		}
	}

	private function configuredProfileMask($value)
	{
		$_ENV['RU_PROFILE_MASK'] = $value;
		include(__DIR__ . '/../../conf/config.php');
		return $profileMask;
	}

	public function testEnvironmentProfileMaskIsParsedAsOctalInteger()
	{
		$mask = $this->configuredProfileMask('0770');
		$this->assertTrue(is_int($mask), 'RU_PROFILE_MASK becomes an integer before permission operations');
		$this->assertEquals(0770, $mask, 'RU_PROFILE_MASK=0770 keeps its documented octal meaning');
	}

	public function testEmptyEnvironmentProfileMaskUsesTheDefault()
	{
		$this->assertEquals(
			0777,
			$this->configuredProfileMask(''),
			'An empty RU_PROFILE_MASK behaves like an unset value instead of crashing requests'
		);
	}

	public function testAMaskThatCouldNotBeReadSaysSo()
	{
		// The fallback is 0777, which is wider than any mask an admin sets one
		// for. Taking it silently turns a typo into world-writable profiles.
		$warned = null;
		set_error_handler(function ($errno, $errstr) use (&$warned) {
			$warned = $errstr;
			return true;
		});
		$mask = $this->configuredProfileMask('02770');
		restore_error_handler();

		$this->assertEquals(0777, $mask, 'a mask that could not be read falls back to the default');
		$this->assertTrue($warned !== null, 'and the fallback is reported');
		$this->assertTrue(strpos($warned, 'RU_PROFILE_MASK') !== false,
			'and the report names the setting: ' . var_export($warned, true));
	}

	public function testAMaskThatIsSimplyUnsetSaysNothing()
	{
		$warned = null;
		set_error_handler(function ($errno, $errstr) use (&$warned) {
			$warned = $errstr;
			return true;
		});
		$mask = $this->configuredProfileMask('');
		restore_error_handler();

		$this->assertEquals(0777, $mask, 'an empty mask uses the documented default');
		$this->assertTrue($warned === null, 'and says nothing, because nothing was asked for');
	}

	public function testInvalidEnvironmentProfileMaskUsesTheDefault()
	{
		// The fallback now reports itself; the handler keeps that out of the
		// suite log without changing what this case asserts.
		set_error_handler(function () { return true; });
		$mask = $this->configuredProfileMask('not-a-mask');
		restore_error_handler();

		$this->assertEquals(
			0777,
			$mask,
			'An invalid RU_PROFILE_MASK cannot reach chmod or mkdir as a string'
		);
	}
}
