<?php

$_ENV['RU_PROFILE_PATH'] = sys_get_temp_dir() . '/rutorrent-rss-fetchurl-' . getmypid();

@define('HISTORY_MAX_COUNT', 100, true);
@define('HISTORY_MAX_TRY', 3, true);
@define('WAIT_AFTER_LOADING', 0, true);

require_once(__DIR__ . '/../../php/TestCase.php');

$minInterval = 2;
$feedsWithIncorrectTimes = array();
$rss_debug_enabled = false;
require_once(__DIR__ . '/../../../plugins/rss/rss.php');

/** Records that it was called, and answers as Snoopy would on a failure. */
function rssFetchURLInjectionMarker($url, $cookies = null, $headers = null)
{
	file_put_contents($GLOBALS['rssInjectionMarkerFile'], "called\n");
	$cli = new stdClass();
	$cli->status = -100;
	$cli->error = 'marker';
	$cli->headers = array();
	$cli->results = '';
	return $cli;
}

/**
 * rRSS keeps the function that performs its HTTP requests in a property, and
 * calls it through call_user_func() in getTorrent() and in fetch(). The
 * property is private, which stops other code reaching it but not a serialized
 * payload: unserialize() restores private properties exactly as it restores
 * public ones.
 *
 * An rRSS is stored in, and read back out of, a cache file. So without this
 * the bytes of that file chose which function the object called, and were
 * handed the feed URL and the stored cookies as its arguments.
 *
 * The seam itself is worth keeping -- six tests in RSSTest.php pass a fetcher
 * to the constructor -- so what changes is where it can be set from. A
 * constructed rRSS takes the caller's function; a restored one is put back on
 * the shipped rssFetchURL, whatever the bytes said.
 */
final class RSSFetchURLTest extends TestCase
{
	private $markerFile;

	public function setUp()
	{
		@mkdir($_ENV['RU_PROFILE_PATH'], 0700, true);
		$this->markerFile = $_ENV['RU_PROFILE_PATH'] . '/marker';
		$GLOBALS['rssInjectionMarkerFile'] = $this->markerFile;
		@unlink($this->markerFile);
	}

	public function tearDown()
	{
		@unlink($this->markerFile);
		$dir = $_ENV['RU_PROFILE_PATH'];
		foreach (array_reverse(glob($dir . '/{,*/,*/*/,*/*/*/}*', GLOB_BRACE)) as $path) {
			is_dir($path) ? @rmdir($path) : @unlink($path);
		}
		@rmdir($dir);
	}

	private function fetcherOf($rss)
	{
		$property = new ReflectionProperty('rRSS', 'fetchURL');
		$property->setAccessible(true);
		return $property->getValue($rss);
	}

	/** The payload: an rRSS naming another function as its fetcher. */
	private function hijacked($fetcher)
	{
		$nul = "\0";
		// version matches what the cache expects, so that the payload is one
		// the loader would have accepted before this change.
		return 'O:4:"rRSS":4:{s:4:"hash";s:32:"' . str_repeat('a', 32) . '";'
			. 's:3:"url";s:21:"http://127.0.0.1:1/x/";'
			. 's:7:"version";i:1;'
			. 's:14:"' . $nul . 'rRSS' . $nul . 'fetchURL";s:' . strlen($fetcher)
			. ':"' . $fetcher . '";}';
	}

	public function testARestoredFeedDoesNotKeepThePayloadsFetcher()
	{
		$rss = unserialize($this->hijacked('rssFetchURLInjectionMarker'));

		$this->assertTrue($rss instanceof rRSS, 'the payload does restore an rRSS');
		$this->assertTrue($this->fetcherOf($rss) === 'rssFetchURL',
			'and its fetcher is the shipped one, not the one in the bytes');
	}

	/** And the function the payload named is not called. */
	public function testTheFetcherNamedByThePayloadIsNeverCalled()
	{
		$rss = unserialize($this->hijacked('rssFetchURLInjectionMarker'));
		$rss->fetch(new rRSSHistory());

		clearstatcache(true, $this->markerFile);
		$this->assertTrue(file_exists($this->markerFile) === false,
			'fetch() did not call the function the payload named');
	}

	/** Any callable, not only a function this file declares. */
	public function testAPayloadCannotNameAnArbitraryFunction()
	{
		$rss = unserialize($this->hijacked('passthru'));

		$this->assertTrue($this->fetcherOf($rss) === 'rssFetchURL',
			'a payload naming passthru gets the shipped fetcher instead');
	}

	/** The same payload read back through the cache, which is the real path. */
	public function testAFeedReadOutOfTheCacheUsesTheShippedFetcher()
	{
		$cache = new rCache('/rss/cache');
		$hash = str_repeat('b', 32);
		$dir = FileUtil::getSettingsPath() . '/rss/cache';
		FileUtil::makeDirectory($dir);
		file_put_contents($dir . '/' . $hash,
			str_replace(str_repeat('a', 32), $hash,
				$this->hijacked('rssFetchURLInjectionMarker')));

		$rss = new rRSS();
		$rss->hash = $hash;
		$loaded = $cache->get($rss);

		$this->assertTrue($loaded === true, 'the feed is loaded');
		$this->assertTrue($this->fetcherOf($rss) === 'rssFetchURL',
			'and it fetches through the shipped function');
	}

	/** The constructor seam RSSTest.php depends on still works. */
	public function testTheConstructorStillTakesAFetcher()
	{
		$rss = new rRSS('http://example.test/feed', 'rssFetchURLInjectionMarker');

		$this->assertTrue($this->fetcherOf($rss) === 'rssFetchURLInjectionMarker',
			'a constructed feed keeps the fetcher its caller passed');
	}
}
