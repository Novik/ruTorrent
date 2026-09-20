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

	// A scheme-relative target takes the scheme of the page and opens, so it is
	// stored rather than deleted. It was refused before ExternalURL::isOpenable()
	// was shared with this path, which is what destroyed it on the next save.
	public function testAcceptsATargetThatTakesTheSchemeOfThePage()
	{
		$this->assertEquals('//example.com/search?q={title}',
			$this->stored('Name', '//example.com/search?q={title}'));
		$this->assertEquals('magnet:?xt=urn:btih:{title}',
			$this->stored('Name', 'magnet:?xt=urn:btih:{title}'));
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
	// The table plugins/rss asserts against. init.js:73 hands a completed
	// template to openExternalURL(), which is the call plugins/rss/init.js makes
	// for a feed item, so the rule for what may be stored here is the rule for
	// what may be kept there and the two read it from the same file.
	private function openableTable()
	{
		$rows = json_decode(file_get_contents(
			__DIR__ . '/../../fixtures/openable-addresses.json'), true);
		$this->assertTrue(is_array($rows) && count($rows) > 0,
			'read the shared address table');
		return $rows;
	}

	// set() rebuilds the whole list from the request body, so a target it
	// refuses is not rejected -- it is deleted from a list the user was just
	// shown, without anything being said.
	public function testStoresExactlyTheTargetsTheSharedTableAllows()
	{
		foreach ($this->openableTable() as $row) {
			// A value is split on '|', so a target holding one cannot be
			// expressed in the body shape init.js:51 posts at all.
			if (strpos($row['url'], '|') !== false) {
				continue;
			}
			$this->assertTrue(($this->stored('Name', $row['url']) !== null) === $row['server'],
				($row['server'] ? 'stored: ' : 'refused: ') .
				json_encode($row['url']) . ' -- ' . $row['why']);
		}
	}

	// The rule the table exists to hold, on this path too. A stored target the
	// browser will not open is a menu entry that does nothing when it is used.
	public function testNoTargetIsStoredThatTheBrowserWouldRefuseToOpen()
	{
		foreach ($this->openableTable() as $row) {
			$this->assertTrue(!($row['server'] && !$row['client']),
				'not a dead menu entry: ' . json_encode($row['url']) . ' -- ' . $row['why']);
		}
	}

	// A save posts back every row the dialog was showing, so an entry the user
	// never touched is destroyed by a save that changed a different one.
	public function testASaveKeepsEveryEntryThatStillWorks()
	{
		$entries = array(
			'Google' => 'https://www.google.com/search?q={title}',
			'Mirror' => '//mirror.example.org/search?q={title}',
			'Files' => 'ftp://files.example.org/{title}',
			'Magnet' => 'magnet:?xt=urn:btih:{title}',
		);
		$body = array('dummy=1');
		foreach ($entries as $name => $url) {
			$body[] = 'look=' . rawurlencode($name . '|' . $url);
		}
		$look = new rLook();
		$look->set(implode('&', $body));
		$this->assertEquals(array_keys($entries), array_keys($look->list));
	}
}
