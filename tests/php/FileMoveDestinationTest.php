<?php

require_once(__DIR__ . '/TestCase.php');

/**
 * Every rtOpFiles() in the tree refuses a destination name that is already
 * taken.
 *
 * rtOpFiles() is the file level mover: it carries a download's files from one
 * directory to another as "Move", "Copy", "HardLink" or "SoftLink". Two
 * plugins ship a copy of it. A copy that unlinks whatever stands at the
 * destination name before writing destroys a file that belongs to something
 * else -- another download moved into the same directory, or a file the user
 * keeps there -- and then reports the operation as a success, so nothing
 * anywhere says the file is gone.
 *
 * tests/plugins/datadir/MoveDestinationTest.php covers one copy against the
 * panel's "Set data directory". This covers all of them, and finds them by
 * scanning rather than by naming them: a third copy -- a plugin vendoring
 * util_rt.php, which is how the second one came about -- is covered the day it
 * is added rather than the day somebody remembers to write a test for it.
 *
 * Each copy is driven in a process of its own. They define the same function
 * name, so one process cannot hold two, and each copy's require_once lines are
 * relative to the working directory and pull in the configuration and a live
 * rtorrent. The copy is byte compared after copying, so what runs is the
 * shipped file, and every copy is asked to do an ordinary move as well, so a
 * probe that silently did nothing fails instead of reading as a refusal.
 */
class FileMoveDestinationTest extends TestCase
{
	private $tree;

	public function setUp()
	{
		$this->tree = sys_get_temp_dir() . '/rutorrent-filemove-' . getmypid();
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

	/** Every file in the tree that defines rtOpFiles(), relative to the root. */
	private function sources()
	{
		$found = array();
		foreach (array('php', 'plugins') as $dir)
		{
			$path = $this->repoRoot() . '/' . $dir;
			if (!is_dir($path))
				continue;
			$items = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
			foreach ($items as $item)
			{
				if (!$item->isFile() || substr($item->getFilename(), -4) !== '.php')
					continue;
				$body = file_get_contents($item->getPathname());
				if (preg_match('/function\s+rtOpFiles\s*\(/i', $body))
					$found[] = substr($item->getPathname(), strlen($this->repoRoot()) + 1);
			}
		}
		sort($found);
		return $found;
	}

	/**
	 * Runs one copy in its own process and returns what it reported, keyed by
	 * case name: array(returned, destination content, source still there).
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

		// Everything below the copy's own boundary: the log sink and the
		// large file helpers it asks about the filesystem through.
		file_put_contents($this->tree . '/php/xmlrpc.php', '<?php
			class FileUtil
			{
				public static $log = array();
				public static function toLog($msg) { self::$log[] = $msg; }
			}
			class LFS
			{
				public static function is_file($p) { return(is_file($p)); }
				public static function stat($p) { return(@stat($p)); }
			}
		');
		file_put_contents($this->tree . '/php/Torrent.php', '<?php');
		file_put_contents($this->tree . '/php/rtorrent.php', '<?php');

		$probe = $this->tree . '/probe.php';
		file_put_contents($probe, '<?php
			chdir(' . var_export($holder, true) . ');
			require_once(' . var_export($to, true) . ');
			$root = ' . var_export($this->tree . "/work", true) . ';
			function put($p, $c) { @mkdir(dirname($p), 0777, true); file_put_contents($p, $c); }
			$out = array();

			// The positive control: an ordinary move into an empty directory.
			put($root . "/control/src/a.bin", "AAA");
			put($root . "/control/src/sub/b.bin", "BBB");
			@mkdir($root . "/control/dst", 0777, true);
			$ok = rtOpFiles(array("a.bin", "sub/b.bin"),
				$root . "/control/src", $root . "/control/dst", "Move");
			$out["control"] = array($ok,
				@file_get_contents($root . "/control/dst/a.bin"),
				@file_get_contents($root . "/control/dst/sub/b.bin"),
				is_file($root . "/control/src/a.bin"));

			// A file of the same name is already at the destination.
			foreach (array("Move", "Copy", "HardLink", "SoftLink") as $op)
			{
				$src = $root . "/" . $op . "/src";
				$dst = $root . "/" . $op . "/dst";
				put($src . "/x.bin", "the download");
				put($dst . "/x.bin", "already there");
				$ok = rtOpFiles(array("x.bin"), $src, $dst, $op);
				$out[$op] = array($ok, @file_get_contents($dst . "/x.bin"),
					is_file($src . "/x.bin"));
			}

			// A directory, and a symlink, standing at the destination name.
			put($root . "/dir/src/x.bin", "the download");
			@mkdir($root . "/dir/dst/x.bin", 0777, true);
			put($root . "/dir/dst/x.bin/kept.txt", "kept");
			$ok = rtOpFiles(array("x.bin"), $root . "/dir/src", $root . "/dir/dst", "Move");
			$out["directory"] = array($ok,
				@file_get_contents($root . "/dir/dst/x.bin/kept.txt"),
				is_file($root . "/dir/src/x.bin"));

			put($root . "/link/src/x.bin", "the download");
			put($root . "/link/target.txt", "already there");
			@mkdir($root . "/link/dst", 0777, true);
			@symlink($root . "/link/target.txt", $root . "/link/dst/x.bin");
			$ok = rtOpFiles(array("x.bin"), $root . "/link/src", $root . "/link/dst", "Move");
			$out["symlink"] = array($ok,
				@file_get_contents($root . "/link/target.txt"),
				is_file($root . "/link/src/x.bin"));

			echo json_encode($out);
		');

		$output = array();
		exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 ' .
			escapeshellarg($probe) . ' 2>&1', $output);
		$printed = implode("\n", $output);
		$result = json_decode($printed, true);
		if (!is_array($result))
			throw new Exception('the probe for ' . $source . ' printed: ' . $printed);
		return $result;
	}

	/**
	 * A scan that finds nothing, or finds one of the two that ship, is a false
	 * green and not a pass.
	 */
	public function testTheScanFindsTheCopiesThatShip()
	{
		$sources = $this->sources();
		$this->assertTrue(count($sources) >= 2,
			'the tree ships more than one rtOpFiles(); found: ' . implode(', ', $sources));
		foreach (array('plugins/datadir/util_rt.php', 'plugins/autotools/util_rt.php') as $known)
			$this->assertTrue(in_array($known, $sources, true),
				$known . ' is among them; found: ' . implode(', ', $sources));
	}

	public function testEveryCopyStillMovesADownload()
	{
		foreach ($this->sources() as $source)
		{
			$r = $this->drive($source);
			$this->assertTrue($r['control'][0] === true, $source . ': an ordinary move succeeds');
			$this->assertEquals('AAA', $r['control'][1], $source . ': the file arrives with its content');
			$this->assertEquals('BBB', $r['control'][2], $source . ': and the one in a subdirectory too');
			$this->assertTrue($r['control'][3] === false, $source . ': and it leaves the source');
		}
	}

	public function testNoCopyWritesOverAFileAlreadyAtTheDestination()
	{
		foreach ($this->sources() as $source)
		{
			$r = $this->drive($source);
			foreach (array('Move', 'Copy', 'HardLink', 'SoftLink') as $op)
			{
				$this->assertTrue($r[$op][0] === false,
					$source . ': ' . $op . ' into a taken name is refused');
				$this->assertEquals('already there', $r[$op][1],
					$source . ': the file that was already there survives ' . $op);
				$this->assertTrue($r[$op][2] === true,
					$source . ': and the download has not left the source after ' . $op);
			}
		}
	}

	public function testNoCopyWritesOverADirectoryOrASymlink()
	{
		foreach ($this->sources() as $source)
		{
			$r = $this->drive($source);
			$this->assertTrue($r['directory'][0] === false,
				$source . ': a directory in the way refuses the move');
			$this->assertEquals('kept', $r['directory'][1],
				$source . ': and what is inside it is still there');
			$this->assertTrue($r['symlink'][0] === false,
				$source . ': a symlink in the way refuses the move');
			$this->assertEquals('already there', $r['symlink'][1],
				$source . ': and what it pointed at is untouched');
		}
	}
}
