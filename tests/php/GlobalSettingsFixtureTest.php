<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/GlobalSettingsFixture.php');

class GlobalSettingsFixtureTest extends TestCase
{
	public function testRestorePreservesAbsentNullAndPopulatedSettings()
	{
		$names = array('scgiFixtureAbsent', 'scgiFixtureNull', 'scgiFixtureValue');
		$previous = array();
		foreach($names as $name)
			$previous[$name] = array(array_key_exists($name, $GLOBALS), $GLOBALS[$name] ?? null);
		try
		{
			unset($GLOBALS['scgiFixtureAbsent']);
			$GLOBALS['scgiFixtureNull'] = null;
			$GLOBALS['scgiFixtureValue'] = array('port' => 5000);
			$fixture = new GlobalSettingsFixture($names);
			$GLOBALS['scgiFixtureAbsent'] = 'created';
			$GLOBALS['scgiFixtureNull'] = 'changed';
			unset($GLOBALS['scgiFixtureValue']);
			$fixture->restore();
			$this->assertTrue(!array_key_exists('scgiFixtureAbsent', $GLOBALS), 'an absent setting becomes absent again');
			$this->assertTrue(array_key_exists('scgiFixtureNull', $GLOBALS) && $GLOBALS['scgiFixtureNull'] === null,
				'a null setting remains present with its original value');
			$this->assertTrue(isset($GLOBALS['scgiFixtureValue']) && $GLOBALS['scgiFixtureValue'] === array('port' => 5000),
				'a removed populated setting regains its original value');
		}
		finally
		{
			// Cleanup is independent of the helper under test.
			foreach($previous as $name => $setting)
				if($setting[0])
					$GLOBALS[$name] = $setting[1];
				else
					unset($GLOBALS[$name]);
		}
	}

	public function testScgiFixtureCoversEverySettingTheTransportReads()
	{
		$previous = array();
		foreach(GlobalSettingsFixture::SCGI as $name)
			$previous[$name] = array(array_key_exists($name, $GLOBALS), $GLOBALS[$name] ?? null);
		try
		{
			foreach(GlobalSettingsFixture::SCGI as $name)
				$GLOBALS[$name] = 'before';
			$fixture = GlobalSettingsFixture::scgi();
			foreach(GlobalSettingsFixture::SCGI as $name)
				$GLOBALS[$name] = 'after';
			$fixture->restore();
			$restored = true;
			foreach(GlobalSettingsFixture::SCGI as $name)
				$restored = $restored && ($GLOBALS[$name] === 'before');
			$this->assertTrue($restored, 'every SCGI setting returns to its value');
			$this->assertTrue(count(array_intersect(array('scgi_host', 'rpcTransferTimeOut', 'rpcMaxResponseBytes',
				'tempDirectory', 'tempDirectory_init_done'), GlobalSettingsFixture::SCGI)) === 5,
				'the list names the transport, budget, limit and temporary directory settings');
		}
		finally
		{
			foreach($previous as $name => $setting)
				if($setting[0])
					$GLOBALS[$name] = $setting[1];
				else
					unset($GLOBALS[$name]);
		}
	}
}
