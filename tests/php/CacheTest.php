<?php

$_ENV['RU_PROFILE_PATH'] = sys_get_temp_dir() . '/rutorrent-cache-test-' . getmypid();

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/cache.php');
require_once(__DIR__ . '/CacheMergePayloadFixture.php');

// Its own class rather than an instance with a reassigned hash: when set()
// reconciles a concurrent write it reloads the file through a freshly
// constructed instance of the same class, so a merge-capable payload has to
// carry its identity in the class, the way rHistoryData does.
class CacheGhostLoadPayload extends CacheMergePayload
{
	public $hash = 'ghost-load-test.dat';
}

class CacheTest extends TestCase
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

	// Writes a helper that does what a second update.php does -- load the
	// cache, record one row, store it -- as a genuinely separate process. It
	// has to be a real process: rCache remembers the loaded file's stamp in a
	// static, so a second writer inside this one would share it.
	private function makeWriterScript()
	{
		$path = FileUtil::getSettingsPath() . '/concurrent-writer.php';
		$code = "<?php\n"
			. '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->profilePath, true) . ";\n"
			. 'require_once(' . var_export(realpath(__DIR__ . '/../../php/cache.php'), true) . ");\n"
			. 'require_once(' . var_export(realpath(__DIR__ . '/CacheMergePayloadFixture.php'), true) . ");\n"
			. "\$payload = new CacheMergePayload();\n"
			. "(new rCache())->get(\$payload);\n"
			. "exit(\$payload->addRow(\$argv[1]) ? 0 : 1);\n";
		file_put_contents($path, $code);
		return $path;
	}

	// Writes the helper for the overlap test: load the cache, then rendezvous
	// with the peer writer -- announce readiness through one file, spin until
	// the peer's file appears -- so both processes enter set() at the same
	// moment, then store one row.
	private function makeBarrierWriterScript()
	{
		$path = FileUtil::getSettingsPath() . '/overlapping-writer.php';
		$code = "<?php\n"
			. '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->profilePath, true) . ";\n"
			. 'require_once(' . var_export(realpath(__DIR__ . '/../../php/cache.php'), true) . ");\n"
			. 'require_once(' . var_export(realpath(__DIR__ . '/CacheMergePayloadFixture.php'), true) . ");\n"
			. "list(, \$row, \$readyMine, \$readyOther) = \$argv;\n"
			. "\$payload = new CacheMergePayload();\n"
			. "(new rCache())->get(\$payload);\n"
			. "touch(\$readyMine);\n"
			. "\$deadline = microtime(true) + 10;\n"
			. "for (;;) {\n"
			. "\tclearstatcache(true, \$readyOther);\n"
			. "\tif (file_exists(\$readyOther)) break;\n"
			. "\tif (microtime(true) > \$deadline) exit(2);\n"
			. "\tusleep(200);\n"
			. "}\n"
			. "// Both writers saw each other; spinning to the same clock boundary\n"
			. "// puts their set() calls within microseconds of each other.\n"
			. "\$go = ceil((microtime(true) + 0.05) * 4) / 4;\n"
			. "while (microtime(true) < \$go);\n"
			. "exit(\$payload->addRow(\$row) ? 0 : 1);\n";
		file_put_contents($path, $code);
		return $path;
	}

	// The live failure this guards: replacing a torrent fires three update.php
	// processes inside one second, and filemtime only resolves to the second,
	// so a writer that reloaded after a same-second write saw an unchanged
	// timestamp, skipped merging and republished the file without the other
	// process's row. On a live fleet the row that vanished was the "added" one
	// of every replacement.
	public function testRowFromAnotherProcessSurvivesAWriteInTheSameSecond()
	{
		// Start near the top of a second so the seed, this process's load and
		// the other process's write all share one mtime.
		usleep((int) ((1 - fmod(microtime(true), 1)) * 1000000));

		$seed = new CacheMergePayload();
		$this->assertTrue($seed->addRow('seed'), 'the seeding write succeeds');

		// This process loads the file; rCache records how the file looked.
		$mine = new CacheMergePayload();
		(new rCache())->get($mine);

		// Another process records its own row in the very same second.
		$writer = $this->makeWriterScript();
		exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($writer) . ' other-process', $output, $code);
		$this->assertEquals(0, $code, 'the second writer process stores its row');

		// Only now does this process store its own row.
		$this->assertTrue($mine->addRow('mine'), 'this process stores its row');

		$check = new CacheMergePayload();
		(new rCache())->get($check);
		$this->assertTrue(isset($check->rows['mine']), 'this process keeps its own row');
		$this->assertTrue(isset($check->rows['other-process']),
			'the row another process wrote in the same second is not overwritten');
		$this->assertTrue(isset($check->rows['seed']), 'the pre-existing row is untouched');
	}

	// Unlike the sequential test above, here the two writers overlap inside
	// set(): both have loaded the same file when they start storing. Checking
	// the stamp outside the writer lock loses a row here -- both compare
	// against the pre-write state, both skip merging, and whichever publishes
	// last erases the other's row. Only a check-merge-publish critical
	// section makes this deterministic, which is why the rounds are asserted
	// individually instead of retried.
	public function testTwoOverlappingWritersBothKeepTheirRows()
	{
		$writer = $this->makeBarrierWriterScript();
		$settings = FileUtil::getSettingsPath();
		$cacheFile = $settings . '/' . (new CacheMergePayload())->hash;
		$readyFirst = $settings . '/first-writer.ready';
		$readySecond = $settings . '/second-writer.ready';

		// One round can miss the race by scheduling luck; ten in a row do not.
		for ($round = 1; $round <= 10; $round++) {
			@unlink($cacheFile);
			@unlink($readyFirst);
			@unlink($readySecond);

			$seed = new CacheMergePayload();
			$this->assertTrue($seed->addRow('seed'), "round {$round}: the seeding write succeeds");

			$php = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($writer);
			$first = proc_open($php . ' first '
				. escapeshellarg($readyFirst) . ' ' . escapeshellarg($readySecond), [], $pipesFirst);
			$second = proc_open($php . ' second '
				. escapeshellarg($readySecond) . ' ' . escapeshellarg($readyFirst), [], $pipesSecond);
			$this->assertEquals(0, proc_close($first), "round {$round}: the first writer stores its row");
			$this->assertEquals(0, proc_close($second), "round {$round}: the second writer stores its row");

			$check = new CacheMergePayload();
			(new rCache())->get($check);
			$this->assertTrue(
				isset($check->rows['first']) && isset($check->rows['second']) && isset($check->rows['seed']),
				"round {$round}: both overlapping writers' rows and the seed survive");
		}
	}

	// A writer whose get() found nothing -- the file did not exist yet -- has
	// no stamp to compare against. It must still notice a file that appeared
	// before its own store and merge with it instead of clobbering it.
	public function testWriterThatLoadedNothingMergesAFileThatAppearedMeanwhile()
	{
		// This writer asks for a cache entry that does not exist yet.
		$mine = new CacheGhostLoadPayload();
		$this->assertEquals(false, (new rCache())->get($mine), 'nothing to load yet');

		// Before it stores, another writer publishes the file.
		$other = new CacheGhostLoadPayload();
		$this->assertTrue($other->addRow('other'), 'the other writer stores its row');

		// The first writer's store must merge with the file that appeared.
		$this->assertTrue($mine->addRow('mine'), 'this writer stores its row');

		$check = new CacheGhostLoadPayload();
		(new rCache())->get($check);
		$this->assertTrue(isset($check->rows['mine']), 'this writer keeps its own row');
		$this->assertTrue(isset($check->rows['other']),
			'the row that appeared while this writer held nothing is merged, not clobbered');
	}

	public function testSetAppliesProfileMaskToLockFile()
	{
		global $profileMask;

		$oldMask = umask(0077);
		$oldProfileMask = $profileMask;
		$profileMask = 0666;
		$payload = new CacheMergePayload();
		$payload->hash = 'masked-cache-test.dat';

		try {
			$stored = (new rCache())->set($payload);
		} finally {
			umask($oldMask);
			$profileMask = $oldProfileMask;
		}

		$lockFile = FileUtil::getSettingsPath() . '/' . $payload->hash . '.lock';
		clearstatcache(true, $lockFile);

		$this->assertTrue($stored, 'Cache set succeeds with a restrictive process umask');
		$this->assertEquals(0666, fileperms($lockFile) & 0666, 'Cache lock file follows the configured profile mask');
	}

	public function testRemoveAlsoRemovesLockFile()
	{
		$payload = new CacheMergePayload();
		$payload->hash = 'remove-cache-test.dat';
		$cache = new rCache();
		$cacheFile = FileUtil::getSettingsPath() . '/' . $payload->hash;
		$lockFile = $cacheFile . '.lock';

		$this->assertTrue($cache->set($payload), 'Cache set creates the cache file');
		$this->assertTrue(file_exists($lockFile), 'Cache set creates the lock file');

		$cache->remove($payload);

		clearstatcache(true, $cacheFile);
		clearstatcache(true, $lockFile);
		$this->assertEquals(false, file_exists($cacheFile), 'Cache remove deletes the cache file');
		$this->assertEquals(false, file_exists($lockFile), 'Cache remove deletes the lock file');
	}
}
