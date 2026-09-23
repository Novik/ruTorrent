<?php

require_once(__DIR__ . '/../../php/TestCase.php');

/**
 * The collector that drains the erasedata queue, run as it is run in
 * production: as its own process, over a directory of list files.
 *
 * A list file is newline-delimited and read back a line at a time, so a file
 * path carrying a line break is more than one entry by the time the collector
 * sees it. The path comes from the torrent: libtorrent accepts a path element
 * that is not empty, not "." or "..", and carries no '/' and no NUL, and a
 * line break is none of those, so the publisher of a torrent chooses what the
 * extra entries say. One element ending in a line break, with the elements
 * after it supplying the separators, names an absolute path anywhere on the
 * host, starting at column 0 of the next line.
 *
 * What the collector does with an entry is unlink it, as whatever user runs
 * it. So the boundary this holds is not the file format: it is that an entry
 * naming something outside the download it belongs to is not a file of that
 * download, whoever wrote the list and whenever they wrote it.
 *
 * update.php is copied rather than included: it runs its work at include time
 * and declares functions, so it can be entered once per process, and its one
 * require_once needs answering by a stub -- the real php/util.php pulls in the
 * configuration and a live rtorrent.
 */
class ListCollectorTest extends TestCase
{
	private $tree;
	private $listPath;
	private $log;

	public function setUp()
	{
		$this->tree = sys_get_temp_dir() . '/rutorrent-collector-' . getmypid();
		$this->wipe();
		mkdir($this->tree . '/plugins/erasedata', 0777, true);
		mkdir($this->tree . '/php', 0777, true);
		mkdir($this->tree . '/settings/erasedata', 0777, true);
		$this->listPath = $this->tree . '/settings/erasedata';
		$this->log = $this->tree . '/collector.log';

		$source = dirname(dirname(dirname(__DIR__))) . '/plugins/erasedata/update.php';
		$target = $this->tree . '/plugins/erasedata/update.php';
		copy($source, $target);
		if (hash_file('sha256', $source) !== hash_file('sha256', $target))
			throw new Exception('could not copy update.php');

		file_put_contents($this->tree . '/php/util.php', '<?php
			class FileUtil
			{
				public static function getSettingsPath()
				{
					return(getenv("ERASEDATA_TEST_SETTINGS"));
				}
				public static function makeDirectory($dir)
				{
					return(@mkdir($dir, 0777, true));
				}
				public static function getPluginConf($name)
				{
					return(\'$erasedebug_enabled = \'.(getenv("ERASEDATA_TEST_DEBUG") === "0" ? "false" : "true")
						.\'; $enableForceDeletion = false;\');
				}
				public static function toLog($msg)
				{
					@file_put_contents(getenv("ERASEDATA_TEST_LOG"), $msg."\n", FILE_APPEND);
				}
			}
		');
	}

	public function tearDown()
	{
		$this->wipe();
	}

	private function wipe()
	{
		if (!is_dir($this->tree))
			return;
		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($this->tree, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($items as $item)
			$item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
		@rmdir($this->tree);
	}

	// -- helpers ------------------------------------------------------------

	private function reset()
	{
		foreach (glob($this->listPath . '/*') as $f)
			@unlink($f);
		@unlink($this->log);
	}

	private function file($path, $content = 'x')
	{
		@mkdir(dirname($path), 0777, true);
		file_put_contents($path, $content);
		return $path;
	}

	/** Write a list file exactly as removewithdata.php writes one. */
	private function queue($hash, $files, $base, $multi, $force = '1')
	{
		$lines = $files;
		$lines[] = $base;
		$lines[] = $multi;
		$lines[] = $force;
		file_put_contents($this->listPath . '/' . $hash . '.list2', implode("\n", $lines) . "\n");
	}

	/** Run the shipped collector the way production runs it: its own process. */
	private function collect($debug = true)
	{
		$command = escapeshellarg(PHP_BINARY) . ' -d display_errors=1 update.php';
		$process = proc_open($command,
			array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
			$pipes,
			$this->tree . '/plugins/erasedata',
			array(
				'ERASEDATA_TEST_SETTINGS' => $this->tree . '/settings',
				'ERASEDATA_TEST_LOG' => $this->log,
				'ERASEDATA_TEST_DEBUG' => $debug ? '1' : '0',
				'PATH' => getenv('PATH'),
			));
		if (!is_resource($process))
			throw new Exception('could not start the collector');
		fclose($pipes[0]);
		$out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		proc_close($process);
		return $out;
	}

	private function logged()
	{
		return is_file($this->log) ? file_get_contents($this->log) : '';
	}

	// -- the injection -------------------------------------------------------

	public function testAnEntryOutsideTheDownloadIsNotUnlinked()
	{
		$this->reset();
		$base = $this->tree . '/data/EvilPack';
		$own = $this->file($base . '/inject');
		$victim = $this->file($this->tree . '/victim/DELETE_ME', 'must survive');

		// What the shipped writer produced for a torrent whose first file's
		// path element ended in a line break and whose following elements
		// spelled out an absolute path. Two entries, one of them the victim.
		$this->queue('A', array($own, $victim), $base, '1');
		$out = $this->collect();

		$this->assertTrue($out === '',
			'the collector runs without a diagnostic: ' . $out);
		$this->assertTrue(is_file($victim),
			'a file outside the download is still there');
		$this->assertTrue(strpos($this->logged(), 'REFUSED, not under ' . $base) !== false,
			'and the collector says it refused it');
	}

	public function testTheDownloadsOwnFilesAreStillDeleted()
	{
		$this->reset();
		$base = $this->tree . '/data/GoodPack';
		$one = $this->file($base . '/one.txt');
		$two = $this->file($base . '/sub/two.txt');

		$this->queue('B', array($one, $two), $base, '1');
		$this->collect();

		$this->assertTrue(!is_file($one), 'the download\'s own file is deleted');
		$this->assertTrue(!is_file($two), 'and the one in its subdirectory too');
		$this->assertTrue(!is_dir($base . '/sub'), 'the emptied subdirectory is removed');
		$this->assertTrue(!is_dir($base), 'and the download directory itself');
	}

	public function testASingleFileDownloadIsStillDeleted()
	{
		$this->reset();
		// For a single-file download the base path is the file, so the one
		// entry equals the base rather than lying below it.
		$movie = $this->file($this->tree . '/data/movie.mkv');

		$this->queue('C', array($movie), $movie, '0');
		$this->collect();

		$this->assertTrue(!is_file($movie), 'a single-file download is deleted');
	}

	public function testARelativeEntryIsNotUnlinked()
	{
		$this->reset();
		// A line break in the middle of a path element leaves the tail of it
		// on a line of its own, with no leading separator.
		$base = $this->tree . '/data/RelPack';
		$own = $this->file($base . '/keep');
		$cwdVictim = $this->file($this->tree . '/plugins/erasedata/relative_victim', 'must survive');

		$this->queue('D', array($own, 'relative_victim'), $base, '1');
		$this->collect();

		$this->assertTrue(is_file($cwdVictim),
			'a relative entry is not resolved against the collector\'s directory');
	}

	public function testAnEntryClimbingOutOfTheDownloadIsNotUnlinked()
	{
		$this->reset();
		$base = $this->tree . '/data/DotPack';
		$own = $this->file($base . '/keep');
		$victim = $this->file($this->tree . '/data/neighbour.bin', 'must survive');

		$this->queue('E', array($own, $base . '/../neighbour.bin'), $base, '1');
		$this->collect();

		$this->assertTrue(is_file($victim),
			'an entry climbing out of the download with .. is not unlinked');
	}

	public function testAListClaimingTheRootIsRefusedWhole()
	{
		$this->reset();
		// The base path is read from the third line from the end, so a line
		// break in the download's own name moves what is read as the base. A
		// base of "/" would put every path on the host inside the download.
		$victim = $this->file($this->tree . '/victim/ROOT_CASE', 'must survive');

		$this->queue('F', array($victim), '/', '1');
		$this->collect();

		$this->assertTrue(is_file($victim), 'a list claiming "/" as its base deletes nothing');
		$this->assertTrue(strpos($this->logged(), 'REFUSED, not a usable base path') !== false,
			'and the collector says why');
	}

	public function testEveryListFileIsStillConsumed()
	{
		$this->reset();
		$base = $this->tree . '/data/Consumed';
		$this->file($base . '/a');
		$this->queue('G', array($base . '/a'), $base, '1');
		$this->queue('H', array($this->tree . '/victim/elsewhere'), '/', '1');
		$this->collect();

		$this->assertTrue(count(glob($this->listPath . '/*.list2')) === 0,
			'a refused list is removed from the queue rather than retried forever');
	}

	// -- a list the previous writer left behind ------------------------------

	// A ".list" was written before the writer refused a path carrying a line
	// break. The last three lines of a list are the base path, the multi-file
	// flag and the deletion mode, so one line break in one path element moves
	// all three and the publisher of the torrent chose them. By the time the
	// collector runs, removewithdata.php has already issued d.delete_tied and
	// d.erase, so there is nothing left to check the declared base against.

	/** A list in the format that preceded the line-break refusal. */
	private function queueLegacy($hash, $files, $base, $multi, $force = '1')
	{
		$lines = $files;
		$lines[] = $base;
		$lines[] = $multi;
		$lines[] = $force;
		file_put_contents($this->listPath . '/' . $hash . '.list', implode("\n", $lines) . "\n");
	}

	public function testALegacyListIsNotActedOn()
	{
		$this->reset();
		// A base the download never had, and a file under it: what the
		// previous writer produced for a torrent whose own name carried a
		// line break, and what a local account can write by hand.
		$base = $this->tree . '/elsewhere';
		$victim = $this->file($base . '/DELETE_ME', 'must survive');

		$this->queueLegacy('L', array($victim), $base, '1');
		$this->collect();

		$this->assertTrue(is_file($victim),
			'a list from the previous writer deletes nothing');
		$this->assertTrue(is_dir($base), 'and its declared base is still there');
	}

	public function testALegacyListIsKeptRatherThanDiscarded()
	{
		$this->reset();
		$base = $this->tree . '/elsewhere';
		$victim = $this->file($base . '/DELETE_ME', 'must survive');
		$this->queueLegacy('M', array($victim), $base, '1');
		$before = file_get_contents($this->listPath . '/M.list');

		$this->collect();

		$this->assertTrue(!is_file($this->listPath . '/M.list'),
			'the list is taken out of the queue');
		$this->assertTrue(is_file($this->listPath . '/M.list.unverified'),
			'and kept under a name the collector does not read, because the '
			. 'files it names are still on disk and it still names them');
		$this->assertTrue(file_get_contents($this->listPath . '/M.list.unverified') === $before,
			'unchanged');
	}

	public function testTheQuarantineIsReportedWithDebugLoggingOff()
	{
		$this->reset();
		$base = $this->tree . '/elsewhere';
		$this->file($base . '/DELETE_ME', 'must survive');
		$this->queueLegacy('N', array($base . '/DELETE_ME'), $base, '1');

		// Someone asked for a deletion that is not going to happen, so they
		// are told whether or not the plugin's debug logging is on.
		$this->collect(false);

		$this->assertTrue(strpos($this->logged(), 'N.list') !== false,
			'the quarantine names the list outside the debug channel: ' . $this->logged());
		$this->assertTrue(strpos($this->logged(), 'N.list.unverified') !== false,
			'and says where it went');
	}

	public function testACurrentListIsStillApplied()
	{
		$this->reset();
		// The same directory, one list of each kind: the current one is acted
		// on and the previous one is not, so the two are told apart rather
		// than the collector having simply stopped.
		$mine = $this->tree . '/data/Mine';
		$own = $this->file($mine . '/one.txt');
		$theirs = $this->tree . '/elsewhere';
		$victim = $this->file($theirs . '/DELETE_ME', 'must survive');

		$this->queue('P', array($own), $mine, '1');
		$this->queueLegacy('Q', array($victim), $theirs, '1');
		$this->collect();

		$this->assertTrue(!is_file($own), 'the current list is applied');
		$this->assertTrue(is_file($victim), 'the previous one is not');
	}

	public function testAQuarantineDoesNotReplaceOneAlreadyThere()
	{
		$this->reset();
		// During a rolling upgrade the previous writer is still running, so it
		// can queue the same hash again after this collector has already put one
		// of its lists aside. A quarantined list is the only thing that still
		// names the files it names, so the second one must not land on top of
		// the first.
		$base = $this->tree . '/elsewhere';
		$this->file($base . '/first', 'must survive');
		$this->file($base . '/second', 'must survive');

		$this->queueLegacy('R', array($base . '/first'), $base, '1');
		$first = file_get_contents($this->listPath . '/R.list');
		$this->collect();

		$this->queueLegacy('R', array($base . '/second'), $base, '1');
		$second = file_get_contents($this->listPath . '/R.list');
		$this->collect();

		$kept = array();
		foreach (glob($this->listPath . '/R.list*') as $f)
			$kept[] = file_get_contents($f);
		$this->assertTrue(in_array($first, $kept, true),
			'the list quarantined first still names the files it named');
		$this->assertTrue(in_array($second, $kept, true),
			'and so does the one quarantined after it');
	}
}
