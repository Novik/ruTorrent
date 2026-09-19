<?php

$_ENV['RU_PROFILE_PATH'] = sys_get_temp_dir() . '/rutorrent-addition-caller-' . getmypid();

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/TorrentSequenceFixtures.php');
require_once(__DIR__ . '/AdditionCallerFixtures.php');
require_once(__DIR__ . '/TorrentAdditionSourceScanner.php');
require_once(__DIR__ . '/../../php/rtorrent.php');

/** Answers the two settings questions the add path asks, without a daemon. */
class AdditionCallerSettings extends rTorrentSettings
{
	public function correctDirectory(&$dir, $resolve_links = false)
	{
		return true;
	}

	public function maxContentSize()
	{
		return 1048576;
	}
}

/**
 * The additions the shipped callers build reach rtorrent.
 *
 * tests/php/TorrentAdditionCommandTest.php covers the other direction: what
 * the guard refuses. It exercises the permitted names, so it says that the
 * allowlist accepts what the allowlist contains, and nothing about whether the
 * tree's own callers are in it. They were not: rTorrent::ADDITION_COMMANDS
 * listed four names and three plugins built additions outside them, so the
 * guard silently dropped every Edit-torrent save, every retracker rewrite and
 * every rutracker_check replacement.
 *
 * plugins/edit/action.php and plugins/retrackers/update.php run d.erase on the
 * torrent and reload it from the edited bytes. A refused reload leaves them
 * with the erase already done, no torrent in rtorrent and no second attempt,
 * so the download is gone. That is what these tests are for.
 *
 * Each addition is read out of the caller's own source, through
 * TorrentAdditionSourceScanner, rather than copied into the test: a test
 * holding its own copy of 'd.custom3.set=1' would keep passing after the
 * caller changed, and would be wrong on any daemon whose alias table spells
 * the command differently. The checker's builder is called outright, because
 * it is a function that returns the list.
 */
class TorrentAdditionCallerTest extends TestCase
{
	use TorrentSequenceFixtures;
	use AdditionCallerFixtures;

	private $base;
	private $logFile;
	private $torrentFile;
	private $previousSettings;

	public function setUp()
	{
		$this->base = $_ENV['RU_PROFILE_PATH'];
		@mkdir($this->base, 0700, true);
		$this->logFile = $this->base . '/rpc.log';
		$this->torrentFile = $this->base . '/fixture.torrent';
		file_put_contents($this->torrentFile, $this->announceOnlyTorrent());

		$this->previousSettings = $this->currentSettings();

		// A closed port on the loopback, so an add that does build a payload
		// writes it to the log and then fails to deliver it anywhere real.
		$GLOBALS['scgi_host'] = '127.0.0.1';
		$GLOBALS['scgi_port'] = 1;
		$GLOBALS['rpcTimeOut'] = 1;
		$GLOBALS['rpcLogCalls'] = true;
		$GLOBALS['rpcLogFaults'] = false;
		$GLOBALS['log_file'] = $this->logFile;
		$GLOBALS['profileMask'] = 0600;
		$GLOBALS['saveUploadedTorrents'] = true;
		$GLOBALS['topDirectory'] = '/';
	}

	public function tearDown()
	{
		$this->restoreSettings($this->previousSettings);
		foreach (glob($this->base . '/*') as $path) {
			@unlink($path);
		}
		@rmdir($this->base);
	}

	/**
	 * installSettingsFor() with correctDirectory() answered, so an add reaches
	 * the point where it builds a payload.
	 */
	private function installAddSettingsFor($iVersion)
	{
		$settings = $this->installSettingsFor($iVersion);
		$reflection = new ReflectionClass('AdditionCallerSettings');
		$add = $reflection->newInstanceWithoutConstructor();
		foreach (array('iVersion', 'apiVersion', 'aliases', 'directory') as $field) {
			$add->{$field} = $settings->{$field};
		}
		$property = new ReflectionProperty('rTorrentSettings', 'theSettings');
		$property->setAccessible(true);
		$property->setValue(null, $add);
		return $add;
	}

	private function takeLog()
	{
		$log = file_exists($this->logFile) ? file_get_contents($this->logFile) : '';
		@unlink($this->logFile);
		return $log;
	}

	/** What one add would have sent, for a given addition list. */
	private function payloadFor($addition)
	{
		$this->takeLog();
		rTorrent::sendTorrent(new Torrent($this->torrentFile), true, true,
			null, null, true, false, true, $addition);
		return $this->takeLog();
	}

	/**
	 * The addition the named file builds, as the file builds it: the element
	 * expressions are read out of the source and each one is evaluated through
	 * the same getCmd() the caller calls, so the list moves with both the
	 * caller and the alias table.
	 */
	private function additionBuiltBy($file)
	{
		$scanner = new TorrentAdditionSourceScanner($this->repoRoot());
		$addition = array();
		$unresolved = array();
		$found = false;
		foreach ($scanner->callSites() as $site) {
			if ($site['file'] !== $file || !count($site['elements'])) {
				continue;
			}
			$found = true;
			foreach ($site['elements'] as $element) {
				$name = TorrentAdditionSourceScanner::commandName($element);
				if ($name === null) {
					$unresolved[] = $element['expr'] . ' -- ' . $element['reason'];
					continue;
				}
				// The value each element carries is the caller's runtime data
				// and is not what the guard reads; the command name is.
				$addition[] = $name . '=test-value';
			}
		}
		return array('found' => $found, 'addition' => $addition, 'unresolved' => $unresolved);
	}

	/**
	 * One caller, under every alias table: its addition is readable, every
	 * element passes the guard, and the list as a whole passes.
	 */
	private function assertCallerSurvivesTheGuard($file, $note)
	{
		foreach ($this->supportedVersions() as $iVersion => $version) {
			$this->installSettingsFor($iVersion);
			$built = $this->additionBuiltBy($file);

			$this->assertTrue($built['found'],
				$file . ' is found building an addition (' . $note . ')');
			$this->assertTrue($built['unresolved'] === array(),
				'on rtorrent ' . $version . ' every element ' . $file . ' builds can be read'
				. (count($built['unresolved'])
					? '; unreadable: ' . implode(' | ', $built['unresolved']) : ''));

			$refused = array();
			foreach ($built['addition'] as $entry) {
				if (!rTorrent::isValidAddition($entry)) {
					$refused[] = $entry;
				}
			}
			$this->assertTrue($refused === array(),
				'on rtorrent ' . $version . ' the guard accepts every addition ' . $file
				. ' builds' . (count($refused) ? '; refused: ' . implode(', ', $refused) : ''));
			$this->assertTrue($this->areValidAdditions($built['addition']) === true,
				'on rtorrent ' . $version . ' the whole list ' . $file . ' passes survives '
				. 'areValidAdditions(), which drops the add on one refused entry');
		}
	}

	// ---- the callers -----------------------------------------------------

	public function testTheEditPluginsAdditionSurvivesTheGuard()
	{
		$this->assertCallerSurvivesTheGuard('plugins/edit/action.php',
			'Edit torrent: d.erase then reload the edited bytes');
	}

	public function testTheRetrackersPluginsAdditionSurvivesTheGuard()
	{
		$this->assertCallerSurvivesTheGuard('plugins/retrackers/update.php',
			'retracker rewrite: d.erase then reload');
	}

	public function testTheRutrackerCheckPluginsAdditionSurvivesTheGuard()
	{
		$this->assertCallerSurvivesTheGuard('plugins/rutracker_check/check.php',
			'torrent replacement: load, then erase the old one');
	}

	public function testTheRssPluginsAdditionSurvivesTheGuard()
	{
		$this->assertCallerSurvivesTheGuard('plugins/rss/rss.php',
			'filter match: throttle and ratio group');
	}

	public function testTheDatadirPluginsAdditionSurvivesTheGuard()
	{
		$this->assertCallerSurvivesTheGuard('plugins/datadir/util_setdir.php',
			'set directory: d.erase then reload with fast resume');
	}

	// ---- the checker's builder, called outright --------------------------

	/**
	 * plugins/rutracker_check/check.php is declarations and one
	 * eval(FileUtil::getPluginConf()), so the class body is taken out of it
	 * and evaluated here -- with the real getCmd(), unlike
	 * tests/plugins/rutracker_check/CheckerTest.php, which stubs getCmd() to
	 * the identity and so cannot see an alias problem at all.
	 */
	private function loadChecker()
	{
		if (class_exists('ruTrackerChecker', false)) {
			return;
		}
		$source = file_get_contents($this->repoRoot() . '/plugins/rutracker_check/check.php');
		$offset = strpos($source, 'class ruTrackerChecker');
		if ($offset === false) {
			throw new RuntimeException('ruTrackerChecker was not found in check.php');
		}
		eval(substr($source, $offset));
	}

	private function buildReplacementAddition($connectionSeed, $throttle, $ratioViews, $existingViews)
	{
		$this->loadChecker();
		$method = new ReflectionMethod('ruTrackerChecker', 'buildReplacementAddition');
		$method->setAccessible(true);
		return $method->invoke(null, $connectionSeed, $throttle, $ratioViews, $existingViews,
			constant('ruTrackerChecker::STE_UPDATED'), 'marker-value');
	}

	/**
	 * The list the checker's own builder returns, run through the guard. Both
	 * of its branches are covered: a view it confirmed against rtorrent's view
	 * list gets view.set_visible, one it could not gets
	 * d.views.push_back_unique.
	 */
	public function testTheCheckersBuilderProducesAnAdditionTheGuardAccepts()
	{
		foreach ($this->supportedVersions() as $iVersion => $version) {
			$this->installSettingsFor($iVersion);

			$confirmed = $this->buildReplacementAddition('seed', 'slow',
				array('rat_1'), array('rat_1' => true));
			$this->assertTrue(count($confirmed) >= 7,
				'on rtorrent ' . $version . ' the builder returns the replacement list; entries: '
				. count($confirmed));
			$this->assertTrue($this->areValidAdditions($confirmed) === true,
				'on rtorrent ' . $version . ' the confirmed-view list the builder returns passes '
				. 'the guard: ' . implode(' | ', $confirmed));

			$unconfirmed = $this->buildReplacementAddition('seed', 'slow',
				array('rat_1'), null);
			$this->assertTrue($this->areValidAdditions($unconfirmed) === true,
				'on rtorrent ' . $version . ' the unconfirmed-view list passes too: '
				. implode(' | ', $unconfirmed));

			$noThrottle = $this->buildReplacementAddition('seed', '', array(), array());
			$this->assertTrue($this->areValidAdditions($noThrottle) === true,
				'on rtorrent ' . $version . ' so does the list with no throttle and no views: '
				. implode(' | ', $noThrottle));
		}
	}

	// ---- erase, then reload ----------------------------------------------

	/**
	 * The damage. plugins/edit/action.php and plugins/retrackers/update.php
	 * both erase before they reload, and neither restores the torrent if the
	 * reload fails -- action.php records theUILang.errorAddTorrent and moves
	 * to the next hash, update.php does not even do that. So for these two a
	 * refused addition is not a failed edit, it is a deleted download.
	 *
	 * The erase and the reload are separate rtorrent calls, so the harness
	 * cannot hold them together without a daemon. What it can do is drive the
	 * reload half with the addition the caller really builds and show a
	 * payload comes out of it, which is the half that was returning false.
	 */
	public function testTheErasingCallersReloadBuildsAPayload()
	{
		foreach (array(
			'plugins/edit/action.php'       => 'the Edit torrent save',
			'plugins/retrackers/update.php' => 'the retracker rewrite',
		) as $file => $what) {
			foreach ($this->supportedVersions() as $iVersion => $version) {
				$this->installSettingsFor($iVersion);
				$built = $this->additionBuiltBy($file);
				$this->installAddSettingsFor($iVersion);

				$payload = $this->payloadFor($built['addition']);
				$this->assertTrue($payload !== '',
					'on rtorrent ' . $version . ' ' . $what . ' reloads the torrent it has '
					. 'already erased, instead of returning false with nothing sent');
				// The load command is aliased too: load_raw_start is
				// load.raw_start from rtorrent 0.9.4 on.
				$this->assertTrue(strpos($payload, getCmd('load_raw_start')) !== false,
					'on rtorrent ' . $version . ' ' . $what . ' sends a '
					. getCmd('load_raw_start') . ' call');
				foreach ($built['addition'] as $entry) {
					$name = substr($entry, 0, strpos($entry, '='));
					$this->assertTrue(strpos($payload, $name) !== false,
						'on rtorrent ' . $version . ' ' . $what . ' carries ' . $name
						. ' into the load call');
				}
			}
		}
	}

	/**
	 * The negative half of the same shape, so the test above is not passing
	 * because sendTorrent() sends a payload whatever it is given: an addition
	 * the guard refuses produces nothing at all, which after the caller's
	 * d.erase is the loss.
	 */
	public function testARefusedAdditionSendsNothingAtAll()
	{
		$this->installAddSettingsFor(0x1012);

		$this->assertTrue($this->payloadFor(array('execute=/bin/sh,-c,id')) === '',
			'a refused addition sends no payload, so a caller that erased first has '
			. 'nothing left to reload');
		$this->assertTrue($this->payloadFor(array(
				getCmd('d.set_throttle_name=') . 'slow', null)) === '',
			'and a null entry refuses the list just the same, which is what the edit '
			. 'plugin used to pass when the throttle plugin was not registered');
	}

	/**
	 * The guard is not loosened to let a caller's null through: a null names
	 * no command, and sendTorrent() would put it into the load call as an
	 * empty parameter. The edit plugin appends its throttle only when it has
	 * one instead.
	 */
	public function testTheGuardStillRefusesANullEntry()
	{
		$this->installSettingsFor(0x1012);
		$this->assertTrue(rTorrent::isValidAddition(null) === false,
			'a null addition names no command and is refused');
		$this->assertTrue($this->areValidAdditions(array(null)) === false,
			'and a list holding one is refused whole');
	}

	/**
	 * plugins/edit/action.php reads d.get_throttle_name only when the throttle
	 * plugin is registered, so on an install without it the throttle is never
	 * assigned. Whatever the plugin does about that, it must not put a
	 * non-string into the addition list.
	 */
	public function testTheEditPluginNeverPutsANonStringInItsAddition()
	{
		$this->installSettingsFor(0x1012);
		$built = $this->additionBuiltBy('plugins/edit/action.php');
		$this->assertTrue($built['unresolved'] === array(),
			'every element of the edit plugin addition is a command it built, not a '
			. 'variable that may be unset'
			. (count($built['unresolved'])
				? '; unreadable: ' . implode(' | ', $built['unresolved']) : ''));
		foreach ($built['addition'] as $entry) {
			$this->assertTrue(is_string($entry) && strpos($entry, '=') !== false,
				'the edit plugin addition entry ' . var_export($entry, true) . ' names a command');
		}
	}
}
