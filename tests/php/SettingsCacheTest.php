<?php

$_ENV['RU_PROFILE_PATH'] = sys_get_temp_dir() . '/rutorrent-settings-cache-test-' . getmypid();

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/settings.php');

/**
 * rTorrentSettings caches itself into share/settings/rtorrent.dat, and
 * php/xmlrpc.php builds its singleton from that file on the first
 * rXMLRPCCommand of every later request. Only obtain() can set linkExist, and
 * only getplugins.php / initplugins.php call obtain(); every other entry point
 * takes the cache at its word. A stored object whose probe failed therefore
 * speaks for the whole install until something probes again -- with an empty
 * alias map, so getCommand() hands back the 0.9.x spelling of every renamed
 * command.
 */
class SettingsCacheTest extends TestCase
{
	private $profilePath;

	public function setUp()
	{
		$this->profilePath = $_ENV['RU_PROFILE_PATH'];
		if (is_dir($this->profilePath)) {
			$this->removeDir($this->profilePath);
		}
		FileUtil::makeDirectory(FileUtil::getSettingsPath());
	}

	public function tearDown()
	{
		if (is_dir($this->profilePath)) {
			$this->removeDir($this->profilePath);
		}
	}

	private function removeDir($dir)
	{
		foreach (array_diff(scandir($dir), ['.', '..']) as $entry) {
			$path = $dir . '/' . $entry;
			if (is_dir($path) && !is_link($path)) {
				$this->removeDir($path);
			} else {
				unlink($path);
			}
		}
		rmdir($dir);
	}

	// The constructor is private and get() is a per-process singleton, so build
	// instances the way RtorrentCompatibilityTest does.
	private function makeSettings($linkExist)
	{
		$reflection = new ReflectionClass('rTorrentSettings');
		$settings = $reflection->newInstanceWithoutConstructor();
		$settings->linkExist = $linkExist;
		if ($linkExist) {
			$settings->version = '0.16.20';
			$settings->iVersion = 0x1014;
			$settings->aliases = array(
				'set_download_rate' => array('name' => 'throttle.global_down.max_rate.set', 'prm' => 1),
			);
		}
		return $settings;
	}

	private function cacheFile()
	{
		return FileUtil::getSettingsPath() . '/rtorrent.dat';
	}

	private function readCached()
	{
		$cached = new ReflectionClass('rTorrentSettings');
		$cached = $cached->newInstanceWithoutConstructor();
		(new rCache())->get($cached);
		return $cached;
	}

	public function testFailedProbeIsNotCached()
	{
		$this->makeSettings(false)->store();
		$this->assertTrue(
			!is_file($this->cacheFile()),
			'A settings object whose probe failed must not be written to the cache'
		);
	}

	public function testFailedProbeReportsThatItStoredNothing()
	{
		$this->assertEquals(
			false,
			$this->makeSettings(false)->store(),
			'store() reports false when it declines to cache a failed probe'
		);
	}

	public function testSuccessfulProbeIsCached()
	{
		$this->assertTrue(
			$this->makeSettings(true)->store() !== false,
			'store() reports success for a settings object with a live link'
		);
		$this->assertTrue(
			is_file($this->cacheFile()),
			'A settings object with a live link is written to the cache'
		);
		$cached = $this->readCached();
		$this->assertTrue($cached->linkExist, 'The cached object carries linkExist');
		$this->assertEquals('0.16.20', $cached->version, 'The cached object carries the probed version');
	}

	// The defect: an outage window, or doneplugins.php finding no cache file at
	// all, replaces a good cache with a failed one, and every request after it
	// inherits the verdict.
	public function testFailedProbeDoesNotOverwriteAGoodCache()
	{
		$this->makeSettings(true)->store();
		$this->makeSettings(false)->store();

		$cached = $this->readCached();
		$this->assertTrue($cached->linkExist, 'A failed probe leaves an existing good cache alone');
		$this->assertEquals(
			'throttle.global_down.max_rate.set',
			$cached->getCommand('set_download_rate'),
			'The cached alias map survives a failed probe, so renamed commands keep resolving'
		);
	}

	// What the tenant meets when the map is lost: the status bar's rate control
	// sends a name rtorrent has not answered to since 0.9.x, and the daemon
	// faults instead of throttling.
	public function testAnEmptyAliasMapSendsLegacyCommandNames()
	{
		$this->assertEquals(
			'set_download_rate',
			$this->makeSettings(false)->getCommand('set_download_rate'),
			'With no alias map getCommand() falls back to the legacy spelling'
		);
	}
}
