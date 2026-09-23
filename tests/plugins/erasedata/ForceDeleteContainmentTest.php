<?php

require_once(__DIR__ . '/../../php/TestCase.php');

/**
 * The collector deletes what is inside the download and nothing else, whether
 * it is deleting the download's files or the whole base path.
 *
 * Every entry of a list is a path the collector unlinks, and with force
 * deletion the base path is removed whole with everything under it. What the
 * paths are made of came from the torrent -- from path elements its publisher
 * chose. Deciding whether one of them lies inside the download by looking at
 * the text of it is not enough: unlink(), rmdir() and the walk down a
 * directory are handed the path as one string and follow every symlink in it,
 * so one directory of the download being a link is a name inside the download
 * deciding what is deleted outside it. The force path is where that costs
 * most, because it visits nothing and simply empties whatever it reaches.
 *
 * tests/plugins/erasedata/ListCollectorTest.php covers the entries that leave
 * the download by their text. This covers the ones that leave it through the
 * filesystem, with force deletion enabled, which is the mode that removes a
 * directory rather than named files.
 *
 * update.php is copied rather than included: it runs its work at include time
 * and declares functions, so it can be entered once per process, and its one
 * require_once needs answering by a stub -- the real php/util.php pulls in the
 * configuration and a live rtorrent.
 */
class ForceDeleteContainmentTest extends TestCase
{
	private $tree;
	private $listPath;
	private $log;

	public function setUp()
	{
		$this->tree = sys_get_temp_dir() . '/rutorrent-forcedelete-' . getmypid();
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

		// Force deletion is off by default and is what these cases are about,
		// so the stub takes it from the environment rather than pinning it.
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
					return(\'$erasedebug_enabled = true; $enableForceDeletion = \'.
						(getenv("ERASEDATA_TEST_FORCE") === "1" ? "true" : "false").\';\');
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
			$item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
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
	private function queue($hash, $files, $base, $multi, $force = '2')
	{
		$lines = $files;
		$lines[] = $base;
		$lines[] = $multi;
		$lines[] = $force;
		file_put_contents($this->listPath . '/' . $hash . '.list2', implode("\n", $lines) . "\n");
	}

	/** Run the shipped collector the way production runs it: its own process. */
	private function collect($force = true)
	{
		$command = escapeshellarg(PHP_BINARY) . ' -d display_errors=1 update.php';
		$process = proc_open($command,
			array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
			$pipes,
			$this->tree . '/plugins/erasedata',
			array(
				'ERASEDATA_TEST_SETTINGS' => $this->tree . '/settings',
				'ERASEDATA_TEST_LOG' => $this->log,
				'ERASEDATA_TEST_FORCE' => $force ? '1' : '0',
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

	// -- the control ---------------------------------------------------------

	/**
	 * Force deletion still does what it is for. Without this every refusal
	 * below could be a collector that deletes nothing at all.
	 */
	public function testForceDeletionStillRemovesTheDownloadWhole()
	{
		$this->reset();
		$base = $this->tree . '/data/GoodPack';
		$own = $this->file($base . '/sub/one.txt');
		// Force deletion removes everything under the base, including what the
		// list does not name -- that is the whole point of the mode.
		$unlisted = $this->file($base . '/sub/left_over.part');
		$neighbour = $this->file($this->tree . '/data/Neighbour/keep.txt', 'must survive');

		$this->queue('A', array($own), $base, '1');
		$out = $this->collect();

		$this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
		$this->assertTrue(!is_dir($base), 'the download directory is gone');
		$this->assertTrue(!is_file($unlisted), 'and what was in it that the list did not name');
		$this->assertTrue(is_file($neighbour), 'and the directory beside it is untouched');
	}

	// -- what the force path must not reach ----------------------------------

	/**
	 * A directory of the download that is a symlink. The recursion must remove
	 * the link as the name it is and leave what it points at.
	 */
	public function testForceDeletionDoesNotEmptyWhatASymlinkPointsAt()
	{
		$this->reset();
		$base = $this->tree . '/data/LinkPack';
		$own = $this->file($base . '/one.txt');
		$outside = $this->tree . '/elsewhere';
		$sentinel = $this->file($outside . '/sentinel.txt', 'must survive');
		symlink($outside, $base . '/away');

		$this->queue('B', array($own), $base, '1');
		$out = $this->collect();

		$this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
		$this->assertTrue(is_file($sentinel),
			'a file where a directory of the download points is still there');
		$this->assertTrue(is_dir($outside),
			'and so is the directory it is in');
		$this->assertTrue(!is_dir($base) && !is_link($base . '/away'),
			'while the download, the symlink included, is gone');
	}

	/**
	 * A base path that is itself a symlink. It is removed whole in this mode,
	 * and a link is a name that belongs to whatever it points at.
	 */
	public function testABasePathThatIsASymlinkIsRefused()
	{
		$this->reset();
		$outside = $this->tree . '/pointed_at';
		$sentinel = $this->file($outside . '/sentinel.txt', 'must survive');
		$base = $this->tree . '/data/LinkedBase';
		@mkdir(dirname($base), 0777, true);
		symlink($outside, $base);

		$this->queue('C', array($base . '/sentinel.txt'), $base, '1');
		$out = $this->collect();

		$this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
		$this->assertTrue(is_file($sentinel), 'what the base points at is still there');
		$this->assertTrue(is_link($base), 'and the link itself is left alone');
		$this->assertTrue(strpos($this->logged(), 'REFUSED') !== false,
			'and the collector says it refused the list');
	}

	/**
	 * Force deletion visits none of the entries, so the entries are what ties
	 * the base a list declares to the download it claims to be. A list whose
	 * entries are not files of its own base is not acted on at all.
	 */
	public function testForceDeletionIsAbandonedWhenAnEntryIsNotUnderTheBase()
	{
		$this->reset();
		$base = $this->tree . '/data/MixedPack';
		$own = $this->file($base . '/one.txt');
		$victim = $this->file($this->tree . '/victim/DELETE_ME', 'must survive');

		$this->queue('D', array($own, $victim), $base, '1');
		$out = $this->collect();

		$this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
		$this->assertTrue(is_file($victim), 'the entry outside the download is still there');
		$this->assertTrue(is_dir($base) && is_file($own),
			'and the download is not removed either -- the whole list is refused');
		$this->assertTrue(strpos($this->logged(), 'force delete of ' . $base . ' abandoned') !== false,
			'and the collector says why');
	}

	/** A list naming no file of its own base gives the base nothing to stand on. */
	public function testForceDeletionIsRefusedWhenNoEntryIsUnderTheBase()
	{
		$this->reset();
		$base = $this->tree . '/data/EmptyClaim';
		$this->file($base . '/one.txt');

		// Only the base line names the download; the entry is somebody else's.
		$victim = $this->file($this->tree . '/victim/OTHER', 'must survive');
		$this->queue('E', array($victim), $base, '1');
		$this->collect();

		$this->assertTrue(is_file($victim), 'the entry outside the download is still there');
		$this->assertTrue(is_dir($base), 'and the base it declared is still there');
	}

	// -- what the ordinary path must not reach -------------------------------

	/**
	 * Without force deletion the entries are unlinked one at a time, and an
	 * entry reached through a symlinked directory of the download is outside
	 * it however the text of the path reads.
	 */
	public function testAnEntryReachedThroughASymlinkIsNotUnlinked()
	{
		$this->reset();
		$base = $this->tree . '/data/SlipPack';
		$own = $this->file($base . '/one.txt');
		$outside = $this->tree . '/next_door';
		$sentinel = $this->file($outside . '/secret.txt', 'must survive');
		symlink($outside, $base . '/away');

		// The text reads as a file of the download, and it is not one.
		$this->queue('F', array($own, $base . '/away/secret.txt'), $base, '1', '1');
		$out = $this->collect(false);

		$this->assertTrue($out === '', 'the collector runs without a diagnostic: ' . $out);
		$this->assertTrue(is_file($sentinel),
			'the file the symlink leads to is still there');
		$this->assertTrue(strpos($this->logged(), 'REFUSED, not under ' . $base) !== false,
			'and the collector says it refused it');
		$this->assertTrue(!is_file($own), 'while the download\'s own file is deleted');
	}

	/**
	 * A directory of the download that is a regular file has nothing under it,
	 * so an entry claiming to be under it is not a file of the download.
	 */
	public function testAnEntryUnderARegularFileIsNotUnlinked()
	{
		$this->reset();
		$base = $this->tree . '/data/FlatPack';
		$own = $this->file($base . '/one.txt');
		$blocker = $this->file($base . '/sub', 'a regular file, not a directory');

		$this->queue('G', array($own, $base . '/sub/two.txt'), $base, '1', '1');
		$this->collect(false);

		$this->assertTrue(strpos($this->logged(), 'REFUSED, not under ' . $base) !== false,
			'an entry under a regular file is refused');
		$this->assertTrue(!is_file($own), 'while the download\'s own file is deleted');
		$this->assertTrue(file_get_contents($blocker) === 'a regular file, not a directory',
			'and the file that is in the way is left as it is');
	}

	/** Every list is still consumed, refused or not. */
	public function testEveryListFileIsStillConsumed()
	{
		$this->reset();
		$base = $this->tree . '/data/Consumed';
		$this->file($base . '/a');
		$this->queue('H', array($base . '/a'), $base, '1');
		$this->queue('I', array($this->tree . '/victim/elsewhere'), '/', '1');
		$this->collect();

		$this->assertTrue(count(glob($this->listPath . '/*.list2')) === 0,
			'a refused list is removed from the queue rather than retried forever');
	}
}
