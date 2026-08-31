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

	public function testInvalidEnvironmentProfileMaskUsesTheDefault()
	{
		$this->assertEquals(
			0777,
			$this->configuredProfileMask('not-a-mask'),
			'An invalid RU_PROFILE_MASK cannot reach chmod or mkdir as a string'
		);
	}
}
