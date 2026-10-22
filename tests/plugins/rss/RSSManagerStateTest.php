<?php

$_ENV['RU_PROFILE_PATH'] = sys_get_temp_dir() . '/rutorrent-rss-state-' . getmypid();

@define('HISTORY_MAX_COUNT', 100, true);
@define('HISTORY_MAX_TRY', 3, true);
@define('WAIT_AFTER_LOADING', 0, true);

require_once(__DIR__ . '/../../php/TestCase.php');

$minInterval = 2;
$feedsWithIncorrectTimes = array();
$rss_debug_enabled = false;
require_once(__DIR__ . '/../../../plugins/rss/rss.php');

/**
 * An update run keeps the rRSSManager it started with, and update() ends with
 * saveState(true): that store takes the feed list from whatever is on disk by
 * then and adds only its own errors. With rssurlrewrite enabled, every feed
 * fetched with items fires the RSSFetched hook, which builds a second
 * rRSSManager in the same process -- and that one loads the feed list again.
 */
final class RSSManagerStateTest extends TestCase
{
	public function setUp()
	{
		$this->freshProfile();
	}

	public function tearDown()
	{
		$this->clearProfile();
	}

	/**
	 * TestCase::run() calls setUp() once for the whole class, and every case
	 * here reads the feed list and the error list that rRSSManager finds on
	 * disk. What the case before it stored is not this case's input, so each
	 * one starts the profile again and the cases can be read, and reordered,
	 * one at a time.
	 */
	private function freshProfile()
	{
		$this->clearProfile();
		@mkdir($_ENV['RU_PROFILE_PATH'], 0700, true);
	}

	private function clearProfile()
	{
		$dir = $_ENV['RU_PROFILE_PATH'];
		foreach (array_reverse(glob($dir . '/{,*/,*/*/,*/*/*/}*', GLOB_BRACE)) as $path) {
			is_dir($path) ? @rmdir($path) : @unlink($path);
		}
		@rmdir($dir);
	}

	private function addFeed($manager, $url)
	{
		$manager->rssList->lst[md5($url)] = array('label' => '', 'auto' => 0, 'enabled' => 1, 'url' => $url);
	}

	/** The cache file rRSSManager keeps its feed and error list in. */
	private function feedListFile()
	{
		return $_ENV['RU_PROFILE_PATH'] . '/settings/rss/cache/info';
	}

	public function testAFeedAddedDuringAnUpdateRunSurvivesItsSaveState()
	{
		$this->freshProfile();
		$seed = new rRSSManager();
		$this->addFeed($seed, 'http://example.invalid/one');
		$seed->saveState(false);

		$update = new rRSSManager();			// rss/update.php starts its run
		$webui = new rRSSManager();			// meanwhile the WebUI adds a feed
		$this->addFeed($webui, 'http://example.invalid/two');
		$webui->saveState(false);
		new rRSSManager();				// the RSSFetched hook builds one and drops it
		$update->rssList->touch();
		$update->saveState(true);			// the end of rRSSManager::update()

		$urls = array_column((new rRSSManager())->rssList->lst, 'url');
		sort($urls);
		$this->assertEquals(array('http://example.invalid/one', 'http://example.invalid/two'), $urls,
			'the feed added during the run is still listed: ' . json_encode($urls));
	}

	public function testAnErrorReportedAfterTogglingAFeedStaysClearedOnRepeatedSaves()
	{
		$this->freshProfile();
		$url = 'http://example.invalid/one';
		$seed = new rRSSManager();
		$this->addFeed($seed, $url);
		$seed->rssList->addError('cantFetchRSS', $url);
		$seed->saveState(false);

		$manager = new rRSSManager();
		$manager->toggleStatus(md5($url));
		$this->assertEquals(1, count($manager->rssList->formatErrors()), 'the response reports the pending error');
		// action.php clears reported errors after the action has saved once.
		$manager->clearErrors();
		for ($save = 1; $save <= 3; $save++) {
			$manager->saveState(false);
			$check = new rRSSManager();
			$this->assertEquals(array(), $check->rssList->formatErrors(),
				'the reported error stays cleared after save ' . $save);
			$this->assertEquals(0, $check->rssList->lst[md5($url)]['enabled'], 'the feed stays disabled');
		}
	}

	public function testAFeedAddedAfterThisManagersOwnStoreSurvivesItsNextSave()
	{
		$this->freshProfile();
		$update = new rRSSManager();
		$this->addFeed($update, 'http://example.invalid/own-store');
		$update->saveState(false);

		$webui = new rRSSManager();
		$this->addFeed($webui, 'http://example.invalid/later-store');
		$webui->saveState(false);
		$update->rssList->touch();
		$update->saveState(true);

		$urls = array_column((new rRSSManager())->rssList->lst, 'url');
		$this->assertTrue(in_array('http://example.invalid/own-store', $urls), 'the manager keeps its own feed');
		$this->assertTrue(in_array('http://example.invalid/later-store', $urls),
			'a newer write is merged even after this manager has stored once');
	}

	/**
	 * The store above is remembered because its rename() put those bytes on
	 * disk. A store whose rename() failed published nothing, so the version
	 * the manager holds is still unstored and the file is still the one it
	 * loaded. Remembering the attempt makes the next store compare bytes it
	 * never published with the file it never replaced, find a difference and
	 * merge: the cleared error comes back from the copy on disk.
	 */
	public function testAStoreWhoseRenameFailedIsNotRememberedAsStored()
	{
		$this->freshProfile();
		$url = 'http://example.invalid/one';
		$seed = new rRSSManager();
		$this->addFeed($seed, $url);
		$seed->rssList->addError('cantFetchRSS', $url);
		$seed->saveState(false);

		$manager = new rRSSManager();
		$this->assertEquals(1, count($manager->rssList->formatErrors()), 'the manager loads the reported error');
		$manager->clearErrors();

		// rename() onto a directory fails, which is a rename failure a test
		// can produce without privileges. The file is moved aside and moved
		// back rather than rewritten, so the inode, mtime and size the
		// manager loaded it under are the same ones on either side of the
		// store that could not publish.
		$path = $this->feedListFile();
		$held = $path . '.held';
		$this->assertTrue(rename($path, $held) && mkdir($path, 0700),
			'the next store cannot publish its temporary file');
		$manager->saveState(false);
		$this->assertTrue(rmdir($path) && rename($held, $path), 'the file is back as it was');
		$this->assertEquals(1, count((new rRSSManager())->rssList->formatErrors()),
			'the store that could not publish changed nothing on disk');

		$manager->saveState(false);

		$this->assertEquals(array(), (new rRSSManager())->rssList->formatErrors(),
			'the error cleared before the failed store stays cleared: '
				. json_encode((new rRSSManager())->rssList->formatErrors()));
	}
}
