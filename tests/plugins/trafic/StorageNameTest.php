<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../../plugins/trafic/stat.php');

class StorageNameTest extends TestCase
{
	public function testAcceptsTheNamesThePluginStores()
	{
		foreach (array('global.csv', 'tracker.example.com', 'A1B2C3D4E5F6', 'name with spaces') as $name) {
			$this->assertEquals(true, rStat::isStorageName($name), 'Accepted: '.json_encode($name));
		}
	}

	public function testRefusesAnythingThatDescribesAPath()
	{
		foreach (array('../../etc/passwd', '/etc/passwd', 'a/b', '.', '..', '', "a\0b", null, array()) as $name) {
			$this->assertEquals(false, rStat::isStorageName($name), 'Refused: '.json_encode($name));
		}
	}
}
