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

// A payload that refuses every merge and counts the attempts. It tells a
// store that went straight through from one that re-read and re-merged the
// file first: the second fails on the refusal.
class CacheMergeRefusingPayload extends CacheMergePayload
{
	public $hash = 'merge-refusing-test.dat';
	public static $merges = 0;

	public function merge($instance, $arg)
	{
		self::$merges++;
		return(false);
	}
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
	// cache, record one row, store it -- as a genuinely separate process,
	// with statics of its own, the way a second update.php has them.
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

	// Writes the helper for the repeated-stamp test: another process that
	// changes one row to a value of the same length, rewrites the file in
	// place under the key lock and puts the mtime back. The inode, the mtime
	// and the size are then exactly what this process loaded -- the stat
	// tuple that two rename-published stores leave behind on an idle ext4,
	// where the next temporary file is handed the inode the first store
	// freed -- forced here so the test does not depend on the allocator.
	private function makeSameStampWriterScript()
	{
		$path = FileUtil::getSettingsPath() . '/same-stamp-writer.php';
		$code = "<?php\n"
			. '$_ENV[\'RU_PROFILE_PATH\'] = ' . var_export($this->profilePath, true) . ";\n"
			. 'require_once(' . var_export(realpath(__DIR__ . '/../../php/cache.php'), true) . ");\n"
			. 'require_once(' . var_export(realpath(__DIR__ . '/CacheMergePayloadFixture.php'), true) . ");\n"
			. "\$payload = new CacheMergePayload();\n"
			. "\$name = FileUtil::getSettingsPath() . '/' . \$payload->hash;\n"
			. "\$lock = fopen(\$name . '.lock', 'c');\n"
			. "if (\$lock === false || !flock(\$lock, LOCK_EX)) exit(2);\n"
			. "\$before = stat(\$name);\n"
			. "if (!(new rCache())->get(\$payload)) exit(3);\n"
			. "\$payload->rows['slot'] = \$argv[1];\n"
			. "\$bytes = serialize(\$payload);\n"
			. "if (strlen(\$bytes) !== \$before['size']) exit(4);\n"
			. "if (file_put_contents(\$name, \$bytes) !== strlen(\$bytes)) exit(5);\n"
			. "if (!touch(\$name, \$before['mtime'])) exit(6);\n"
			. "clearstatcache(true, \$name);\n"
			. "\$after = stat(\$name);\n"
			. "exit(\$after['ino'] === \$before['ino'] && \$after['mtime'] === \$before['mtime']\n"
			. "\t&& \$after['size'] === \$before['size'] ? 0 : 7);\n";
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

	// What rCache remembers about a load belongs to the object that was
	// loaded, not to its cache key. One process can hold two objects of one
	// key: an rss update run loads "info" in its rRSSManager, and with
	// rssurlrewrite enabled each feed fetched with items fires the
	// RSSFetched hook, which builds a second rRSSManager that loads "info"
	// again. A load through the second object must not stand for the first,
	// or the first stores as if it had seen a version it never merged and
	// erases what that version added.
	public function testTwoObjectsOfOneKeyKeepTheirOwnLoadedVersions()
	{
		$path = FileUtil::getSettingsPath() . '/' . (new CacheMergePayload())->hash;
		@unlink($path);
		$seed = new CacheMergePayload();
		$this->assertTrue($seed->addRow('seed'), 'the seeding write succeeds');

		$cache = new rCache();
		$first = new CacheMergePayload();
		$this->assertTrue($cache->get($first), 'the first object loads the seeded file');

		// Another process stores a row; then a second object of the same key
		// is loaded in this process and sees it.
		$writer = $this->makeWriterScript();
		exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($writer) . ' other-process', $output, $code);
		$this->assertEquals(0, $code, 'the other process stores its row');
		$second = new CacheMergePayload();
		$this->assertTrue($cache->get($second), 'a second object of the same key loads the newer file');

		// The first object still holds the seeded version.
		$this->assertTrue($first->addRow('mine'), 'the first object stores its row');

		$check = new CacheMergePayload();
		$this->assertTrue($cache->get($check), 'the stored file loads');
		$this->assertTrue(isset($check->rows['other-process']),
			'the row the first object never loaded is merged, not erased by its stale copy');
		$this->assertTrue(isset($check->rows['seed'], $check->rows['mine']),
			'the seed row and the first object\'s row are both in the file');
	}

	// An object that loaded nothing merges whatever is on disk when it
	// stores, whether or not another object of its key was loaded -- also
	// when that object has been freed and PHP hands its object id to the
	// next object it creates.
	public function testAnObjectThatLoadedNothingIsNotTakenForOneThatDid()
	{
		$path = FileUtil::getSettingsPath() . '/' . (new CacheMergePayload())->hash;
		@unlink($path);
		$seed = new CacheMergePayload();
		$this->assertTrue($seed->addRow('seed'), 'the seeding write succeeds');
		$cache = new rCache();

		$peer = new CacheMergePayload();
		$this->assertTrue($cache->get($peer), 'an object of this key loads the file');
		$fresh = new CacheMergePayload();
		$this->assertTrue($fresh->addRow('fresh'), 'a fresh object of the same key stores its row');
		$check = new CacheMergePayload();
		$cache->get($check);
		$this->assertTrue(isset($check->rows['seed'], $check->rows['fresh']),
			'a fresh object merges the file another live object loaded');

		$gone = new CacheMergePayload();
		$this->assertTrue($cache->get($gone), 'another object of this key loads the file');
		$id = spl_object_id($gone);
		unset($gone);
		$reused = new CacheMergePayload();
		$this->assertEquals($id, spl_object_id($reused),
			'the next object takes the freed object id (precondition)');
		$this->assertTrue($reused->addRow('reused'), 'that object stores its row');
		$check = new CacheMergePayload();
		$cache->get($check);
		$this->assertTrue(isset($check->rows['seed'], $check->rows['fresh'], $check->rows['reused']),
			'an object that inherits a freed object id does not inherit its load');
	}

	// The stamp is (inode, mtime, size), and all three can repeat for
	// different bytes: mtime resolves to the second, size repeats whenever a
	// row changes to a value of the same length, and the inode comes back
	// because set() publishes by rename(), which frees the loaded inode, and
	// the next writer's temporary file is handed that number again (12 of 12
	// times on an idle ext4). A writer whose stamp still matched skipped the
	// merge and published its stale copy over both newer ones.
	public function testSameStampWithDifferentBytesFromAnotherProcessStillMerges()
	{
		// setUp() runs once for the class, so the file and this process's
		// record of it carry over from the tests above; seed a fresh file.
		$path = FileUtil::getSettingsPath() . '/' . (new CacheMergePayload())->hash;
		@unlink($path);
		$seed = new CacheMergePayload();
		$seed->rows['slot'] = 'aaaa';
		$this->assertTrue((new rCache())->set($seed), 'the seeding write succeeds');

		// This process loads the file; rCache records how it looked.
		$mine = new CacheMergePayload();
		$this->assertTrue((new rCache())->get($mine), 'this process loads the file');
		$before = stat($path);
		$bytesBefore = file_get_contents($path);

		// Another process changes the row under exactly that stamp.
		$writer = $this->makeSameStampWriterScript();
		exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($writer) . ' bbbb', $output, $code);
		$this->assertEquals(0, $code, 'the other process rewrites the row under the loaded stamp (exit ' . $code . ')');
		clearstatcache(true, $path);
		$after = stat($path);
		$this->assertEquals([$before['ino'], $before['mtime'], $before['size']],
			[$after['ino'], $after['mtime'], $after['size']],
			'inode, mtime and size are the ones this process loaded');
		$this->assertTrue($bytesBefore !== file_get_contents($path), 'and the bytes are not');

		// Only now does this process store its own row.
		$this->assertTrue($mine->addRow('mine'), 'this process stores its row');

		$check = new CacheMergePayload();
		(new rCache())->get($check);
		$this->assertEquals('bbbb', isset($check->rows['slot']) ? $check->rows['slot'] : null,
			'the row another process changed under a repeated stamp is merged, not overwritten');
		$this->assertTrue(isset($check->rows['mine']), 'this process keeps its own row');
	}

	// The other half of the same decision: a stamp that repeats over the
	// bytes this process loaded means the file is still the one it loaded,
	// and the store goes through without re-reading and re-merging it.
	public function testSameStampWithTheSameBytesStoresWithoutReMerging()
	{
		$path = FileUtil::getSettingsPath() . '/' . (new CacheMergeRefusingPayload())->hash;
		@unlink($path);
		$seed = new CacheMergeRefusingPayload();
		$seed->rows['seed'] = 'seed';
		$this->assertTrue((new rCache())->set($seed), 'the seeding write succeeds');
		$mine = new CacheMergeRefusingPayload();
		$this->assertTrue((new rCache())->get($mine), 'this process loads the file');
		CacheMergeRefusingPayload::$merges = 0;

		// Nothing touches the file in between.
		$this->assertTrue($mine->addRow('mine'), 'a store over the unchanged file goes through');
		$this->assertEquals(0, CacheMergeRefusingPayload::$merges,
			'the unchanged file is not re-merged (merge() was called ' . CacheMergeRefusingPayload::$merges . ' times)');

		$check = new CacheMergeRefusingPayload();
		$this->assertTrue((new rCache())->get($check), 'the stored file loads');
		$this->assertTrue(isset($check->rows['seed'], $check->rows['mine']),
			'the seed row and this process\'s row are both in the file');
	}

	// With the stamp repeated, the bytes decide. When they cannot be read,
	// this process cannot tell whether the file is still the one it loaded,
	// and publishing would silently erase a version it could not compare:
	// the store is refused, and the refusal is logged.
	public function testFileThatCannotBeReadUnderTheSameStampIsNotOverwritten()
	{
		global $log_file;

		$path = FileUtil::getSettingsPath() . '/' . (new CacheMergePayload())->hash;
		@unlink($path);
		$seed = new CacheMergePayload();
		$seed->rows['slot'] = 'aaaa';
		$this->assertTrue((new rCache())->set($seed), 'the seeding write succeeds');
		$mine = new CacheMergePayload();
		$this->assertTrue((new rCache())->get($mine), 'this process loads the file');
		$bytes = file_get_contents($path);

		// chmod changes the ctime only: the (inode, mtime, size) stamp stays.
		$this->assertTrue(chmod($path, 0000), 'the file is made unreadable');
		$this->assertEquals(false, @file_get_contents($path),
			'the file really cannot be read (not running as root)');
		$log = $this->profilePath . '/cache-refusal.log';
		$previousLog = $log_file;
		$log_file = $log;
		try {
			$stored = $mine->addRow('mine');
		} finally {
			$log_file = $previousLog;
			chmod($path, 0600);
		}
		$lines = is_file($log) ? file_get_contents($log) : '';

		$this->assertEquals(false, $stored,
			'a store that cannot compare the file with what it loaded is refused');
		$this->assertEquals($bytes, file_get_contents($path), 'the file is left exactly as it was');
		$this->assertTrue(strpos($lines, 'rCache: ' . $mine->hash
			. ' cannot be read to compare with the loaded version; not stored.') !== false,
			'the refusal is logged: ' . json_encode($lines));
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

	public function testRemoveKeepsTheLockForWritersThatAlreadyOpenedIt()
	{
		$payload = new CacheMergePayload();
		$payload->hash = 'remove-cache-test.dat';
		$cache = new rCache();
		$cacheFile = FileUtil::getSettingsPath() . '/' . $payload->hash;
		$lockFile = $cacheFile . '.lock';

		$this->assertTrue($cache->set($payload), 'Cache set creates the cache file');
		$this->assertTrue(file_exists($lockFile), 'Cache set creates the lock file');

		$opened = fopen($lockFile, 'c');
		try {
			$identity = fstat($opened);
			$this->assertTrue($cache->remove($payload), 'Cache remove deletes an existing entry');
			clearstatcache(true, $cacheFile);
			clearstatcache(true, $lockFile);
			$this->assertEquals(false, file_exists($cacheFile), 'Cache remove deletes the cache file');
			$this->assertTrue(file_exists($lockFile), 'Cache remove keeps the lock for waiting writers');
			$later = fopen($lockFile, 'c');
			try {
				$current = fstat($later);
				$this->assertEquals($identity['ino'], $current['ino'],
					'An already-open writer and a later writer use the same lock inode');
				$this->assertTrue(flock($opened, LOCK_EX | LOCK_NB), 'The earlier writer acquires the key lock');
				$this->assertEquals(false, flock($later, LOCK_EX | LOCK_NB),
					'A later writer cannot enter while the earlier writer holds the key lock');
			} finally {
				flock($later, LOCK_UN);
				fclose($later);
			}
		} finally {
			flock($opened, LOCK_UN);
			fclose($opened);
		}
		$this->assertEquals(false, $cache->remove($payload),
			'Removing an absent entry retains the existing false result');
	}

	public function testRemoveDoesNotDeleteWhileAnotherProcessHoldsTheKeyLock()
	{
		$payload = new CacheMergePayload();
		$payload->hash = 'held-remove-cache-test.dat';
		$cache = new rCache();
		$cacheFile = FileUtil::getSettingsPath() . '/' . $payload->hash;
		$this->assertTrue($cache->set($payload), 'The cache entry exists before the competing writer');
		$bytes = file_get_contents($cacheFile);
		$worker = '$lock = fopen($argv[1], "c");'
			. 'if ($lock === false || !flock($lock, LOCK_EX)) exit(2);'
			. 'echo "locked\\n"; fflush(STDOUT);'
			. 'fgets(STDIN); flock($lock, LOCK_UN); fclose($lock);';
		$process = proc_open(array(PHP_BINARY, '-r', $worker, $cacheFile . '.lock'),
			array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
		if(!is_resource($process))
			throw new RuntimeException('Could not start the competing cache writer');
		try {
			stream_set_timeout($pipes[1], 5);
			if(fgets($pipes[1]) !== "locked\n")
				throw new RuntimeException('The competing cache writer did not acquire its lock');
			$this->assertEquals(false, $cache->remove($payload),
				'Remove refuses while a real competing writer holds the key lock');
			$this->assertEquals($bytes, @file_get_contents($cacheFile),
				'A refused remove leaves the competing writer\'s cache bytes intact');
		} finally {
			fclose($pipes[0]);
			$errors = stream_get_contents($pipes[2]);
			fclose($pipes[1]);
			fclose($pipes[2]);
			$exit = proc_close($process);
			$this->assertEquals('', $errors, 'The competing cache writer emits no errors');
			$this->assertEquals(0, $exit, 'The competing cache writer exits after the release signal');
		}
		$this->assertTrue($cache->remove($payload), 'Removal succeeds after the competing writer releases the lock');
	}

	public function testRemoveDoesNotDeleteWhenTheLockCannotBeOpened()
	{
		$payload = new CacheMergePayload();
		$payload->hash = 'unopenable-remove-cache-test.dat';
		$cacheFile = FileUtil::getSettingsPath() . '/' . $payload->hash;
		$bytes = serialize($payload);
		file_put_contents($cacheFile, $bytes);
		mkdir($cacheFile . '.lock', 0700);
		$this->assertEquals(false, @(new rCache())->remove($payload),
			'Remove refuses when a directory prevents opening the key lock');
		$this->assertEquals($bytes, @file_get_contents($cacheFile),
			'A lock-open refusal leaves the removable cache entry intact');
	}
}
