<?php

// The look-at list is rebuilt from the request body by rLook::set(), emitted
// into the page by plugins/lookat/init.php and handed to window.open() by
// plugins/lookat/init.js:73, so whatever set() accepts is what gets opened.

$_ENV['RU_PROFILE_PATH'] = sys_get_temp_dir() . '/rutorrent-lookat-test-' . getmypid();

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../../plugins/lookat/lookat.php');

class LookAtListTest extends TestCase
{
	public function setUp()
	{
		FileUtil::makeDirectory(FileUtil::getSettingsPath());
	}

	public function tearDown()
	{
		$path = $_ENV['RU_PROFILE_PATH'];
		foreach (glob($path . '/*/*') as $file) {
			@unlink($file);
		}
		foreach (glob($path . '/*') as $dir) {
			@rmdir($dir);
		}
		@rmdir($path);
	}

	// One entry, in the body shape plugins/lookat/init.js:51 posts.
	private function stored($name, $url)
	{
		$look = new rLook();
		$look->set('dummy=1&look=' . rawurlencode($name . '|' . $url));
		return array_key_exists($name, $look->list) ? $look->list[$name] : null;
	}

	public function testAcceptsAnHttpTarget()
	{
		$this->assertEquals('https://example.com/search?q={title}',
			$this->stored('Name', 'https://example.com/search?q={title}'));
		$this->assertEquals('http://example.com/s?q={title}',
			$this->stored('Name', 'http://example.com/s?q={title}'));
		$this->assertEquals('HTTPS://example.com/s?q={title}',
			$this->stored('Name', 'HTTPS://example.com/s?q={title}'));
	}

	public function testStillAppendsTheTitlePlaceholderWhenItIsMissing()
	{
		$this->assertEquals('https://example.com/search?q={title}',
			$this->stored('Name', 'https://example.com/search?q='));
	}

	public function testRefusesATargetThatIsNotAnHttpOne()
	{
		foreach (array(
			'javascript:window.x=1;//',
			'JavaScript:window.x=1;//',
			'  javascript:window.x=1;//',
			'data:text/html,<img src=x onerror=1>',
			'vbscript:msgbox(1)',
			'file:///etc/passwd',
			'//example.com/search?q=',
			'example.com/search?q=',
		) as $url) {
			$this->assertTrue($this->stored('Name', $url) === null,
				'refused look-at target '.json_encode($url));
		}
	}

	public function testKeepsTheEntriesItAcceptsAndDropsOnlyTheRest()
	{
		$look = new rLook();
		$look->set(implode('&', array(
			'look=' . rawurlencode('Good|https://example.com/a?q={title}'),
			'look=' . rawurlencode('Bad|javascript:window.x=1;//{title}'),
			'look=' . rawurlencode('AlsoGood|https://example.net/b?q={title}'),
		)));
		$this->assertEquals(array('Good', 'AlsoGood'), array_keys($look->list));
	}
}
