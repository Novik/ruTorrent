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
		@mkdir($_ENV['RU_PROFILE_PATH'], 0700, true);
	}

	public function tearDown()
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

	public function testAFeedAddedDuringAnUpdateRunSurvivesItsSaveState()
	{
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
}
