<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/utility/utility.php');

class UtilityTest extends TestCase
{
	public function testAcceptsDottedHostNames()
	{
		foreach (array('tracker.example.com', 'a.bc', 'my-tracker.example.co.uk', 'XYZ.Example.COM') as $name) {
			$this->assertEquals(true, Utility::isHostname($name), 'Accepted: '.json_encode($name));
		}
	}

	public function testRefusesWhatIsNotAHostName()
	{
		foreach (array(
			'', 'localhost', 'example.com/../x', 'example.com:8080', 'http://example.com',
			'exa mple.com', '-example.com', 'example-.com', 'example..com', '.example.com',
			'example.com.', "example.com\nHost: x", str_repeat('a.', 200).'com', null, 42,
		) as $name) {
			$this->assertEquals(false, Utility::isHostname($name), 'Refused: '.json_encode($name));
		}
	}
}
