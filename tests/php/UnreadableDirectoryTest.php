<?php

require_once(__DIR__ . '/TestCase.php');

/**
 * A directory that cannot be opened, met while walking a tree.
 *
 * rtScanFiles() walks a watch directory for .torrent files and
 * rtRemoveDirectory() walks a download's source directory to clear it after a
 * move. Either can meet a directory it is not allowed to read. opendir() then
 * returns false, and handing false to readdir() is a TypeError on PHP 8: the
 * whole watch or move script dies half way instead of skipping the one
 * directory it cannot see into.
 *
 * Every copy of util_rt.php in the tree is covered, found by scanning, and each
 * runs in a process of its own because they define the same function names.
 * The probe runs with every error displayed, so a warning counts as a failure
 * as well as a fatal does.
 *
 * Needs a user that directory permissions apply to: as root an unreadable
 * directory can still be opened, and the precondition check below fails.
 */
class UnreadableDirectoryTest extends TestCase
{
	private $tree;

	public function setUp()
	{
		$this->tree = sys_get_temp_dir() . '/rutorrent-unreadable-' . getmypid();
		$this->wipe();
	}

	public function tearDown()
	{
		$this->wipe();
	}

	private function wipe()
	{
		if (!is_dir($this->tree))
			return;
		exec('chmod -R u+rwx ' . escapeshellarg($this->tree) . ' 2>/dev/null');
		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($this->tree, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($items as $item)
			$item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
		@rmdir($this->tree);
	}

	private function repoRoot()
	{
		return dirname(dirname(__DIR__));
	}

	/** Every file in the tree that defines rtRemoveDirectory(), relative to the root. */
	private function sources()
	{
		$found = array();
		foreach (array('php', 'plugins') as $dir)
		{
			$items = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($this->repoRoot() . '/' . $dir, FilesystemIterator::SKIP_DOTS));
			foreach ($items as $item)
			{
				if (!$item->isFile() || substr($item->getFilename(), -4) !== '.php')
					continue;
				if (preg_match('/function\s+rtRemoveDirectory\s*\(/i', file_get_contents($item->getPathname())))
					$found[] = substr($item->getPathname(), strlen($this->repoRoot()) + 1);
			}
		}
		sort($found);
		return $found;
	}

	/**
	 * Runs one copy in its own process. Returns array(results, anything else
	 * the process printed); results is null when the process never got as far
	 * as reporting them.
	 */
	private function drive($source)
	{
		$this->wipe();
		$holder = $this->tree . '/' . dirname($source);
		@mkdir($holder, 0777, true);
		@mkdir($this->tree . '/php', 0777, true);

		$from = $this->repoRoot() . '/' . $source;
		$to = $holder . '/' . basename($source);
		copy($from, $to);
		if (hash_file('sha256', $from) !== hash_file('sha256', $to))
			throw new Exception('could not copy ' . $source);

		file_put_contents($this->tree . '/php/xmlrpc.php', '<?php
			class LFS
			{
				public static function is_file($p) { return(is_file($p)); }
			}
		');
		file_put_contents($this->tree . '/php/Torrent.php', '<?php');
		file_put_contents($this->tree . '/php/rtorrent.php', '<?php');

		$probe = $this->tree . '/probe.php';
		file_put_contents($probe, '<?php
			chdir(' . var_export($holder, true) . ');
			require_once(' . var_export($to, true) . ');
			$root = ' . var_export($this->tree . '/work', true) . ';
			$locked = array();
			function put($p, $c) { @mkdir(dirname($p), 0777, true); file_put_contents($p, $c); }
			function lock($p) { global $locked; chmod($p, 0); $locked[] = $p; }
			register_shutdown_function(function () {
				global $locked;
				foreach ($locked as $p)
					@chmod($p, 0755);
			});
			$out = array();

			// The positive control: a tree of empty directories goes.
			@mkdir($root . "/control/a/b", 0777, true);
			@mkdir($root . "/control/c", 0777, true);
			$out["control"] = array(rtRemoveDirectory($root . "/control"),
				is_dir($root . "/control"));

			// An unreadable directory with a file in it, next to an empty one.
			@mkdir($root . "/rm/empty", 0777, true);
			put($root . "/rm/locked/kept.bin", "kept");
			lock($root . "/rm/locked");
			$out["precondition"] = (@opendir($root . "/rm/locked") === false);
			echo "--begin--\n";
			$out["nested"] = array(rtRemoveDirectory($root . "/rm"),
				is_dir($root . "/rm/empty"), is_dir($root . "/rm/locked"));

			// The directory asked for is the one that cannot be opened.
			put($root . "/top/kept.bin", "kept");
			lock($root . "/top");
			$out["top"] = array(rtRemoveDirectory($root . "/top"), is_dir($root . "/top"));

			// A watch directory with an unreadable subdirectory in it.
			put($root . "/scan/a.torrent", "a");
			put($root . "/scan/sub/c.torrent", "c");
			put($root . "/scan/locked/b.torrent", "b");
			lock($root . "/scan/locked");
			$files = rtScanFiles($root . "/scan", "/\\\\.torrent\$/i");
			sort($files);
			$out["scan"] = $files;

			// And a watch directory that cannot be opened at all.
			put($root . "/scantop/a.torrent", "a");
			lock($root . "/scantop");
			$out["scantop"] = rtScanFiles($root . "/scantop", "/\\\\.torrent\$/i");

			echo "--end--\n" . json_encode($out);
		');

		$output = array();
		exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=-1 -d display_errors=1 -d log_errors=0 -d html_errors=0 -d xdebug.mode=off '
			. escapeshellarg($probe) . ' 2>&1', $output);
		$printed = implode("\n", $output);
		$begin = strpos($printed, "--begin--\n");
		$end = strrpos($printed, "--end--\n");
		if ($begin === false || $end === false)
			return array(null, $printed);
		$result = json_decode(substr($printed, $end + strlen("--end--\n")), true);
		$noise = trim(substr($printed, $begin + strlen("--begin--\n"), $end - $begin - strlen("--begin--\n")));
		return array($result, $noise);
	}

	public function testTheScanFindsTheCopiesThatShip()
	{
		$sources = $this->sources();
		foreach (array('plugins/datadir/util_rt.php', 'plugins/autotools/util_rt.php') as $known)
			$this->assertTrue(in_array($known, $sources, true),
				$known . ' is among them; found: ' . implode(', ', $sources));
	}

	public function testNoCopyStopsAtADirectoryItCannotOpen()
	{
		foreach ($this->sources() as $source)
		{
			list($r, $noise) = $this->drive($source);
			$this->assertTrue(is_array($r), $source . ': the walk runs to the end; it printed: ' . $noise);
			if (!is_array($r))
				continue;
			$this->assertTrue($r['precondition'] === true,
				$source . ': the locked directory really cannot be opened (not running as root)');
			$this->assertEquals('', $noise, $source . ': and nothing is printed on the way');

			$this->assertTrue($r['control'][0] === true && $r['control'][1] === false,
				$source . ': a tree of empty directories is removed');

			$this->assertTrue($r['nested'][0] === false,
				$source . ': a tree holding an unreadable directory is reported as not removed');
			$this->assertTrue($r['nested'][1] === false,
				$source . ': the empty directory beside it is still cleared');
			$this->assertTrue($r['nested'][2] === true,
				$source . ': and the unreadable one is left where it is');

			$this->assertTrue($r['top'][0] === false && $r['top'][1] === true,
				$source . ': an unreadable directory holding a file is reported as not removed');

			$this->assertEquals(array('a.torrent', 'sub/c.torrent'), $r['scan'],
				$source . ': a scan returns what it can read and skips what it cannot');
			$this->assertEquals(array(), $r['scantop'],
				$source . ': a scan of a directory it cannot open finds nothing');
		}
	}
}
