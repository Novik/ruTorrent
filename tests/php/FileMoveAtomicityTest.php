<?php

require_once(__DIR__ . '/TestCase.php');

/**
 * A refused move leaves nothing behind: every rtOpFiles() in the tree decides
 * before it carries the first file.
 *
 * rtOpFiles() walks a download's files and operates on them one at a time, and
 * the occupied-destination refusal sits inside that walk. A download whose
 * second file collides has therefore had its first file moved already when the
 * refusal happens, and false is returned with the download split across two
 * directories: half at the source, half at a destination nothing is pointing
 * at. No caller puts it back -- every one of them reads false as "the move did
 * not happen" -- so the download is left unopenable with no record of why.
 *
 * tests/php/FileMoveDestinationTest.php covers the refusal itself for every
 * copy that ships. This covers what the refusal leaves behind, and finds the
 * copies the same way, by scanning rather than by naming them, so a third copy
 * is covered the day it is added.
 *
 * Each copy is driven in a process of its own: they define the same function
 * name, so one process cannot hold two. The copy is byte compared after
 * copying, so what runs is the shipped file, and a list with no collision in
 * it is moved as well, so a probe that silently did nothing fails instead of
 * reading as a refusal.
 */
class FileMoveAtomicityTest extends TestCase
{
	private $tree;

	public function setUp()
	{
		$this->tree = sys_get_temp_dir() . '/rutorrent-moveatomic-' . getmypid();
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
				if (preg_match('/function\s+rtOpFiles\s*\(/i', file_get_contents($item->getPathname())))
					$found[] = substr($item->getPathname(), strlen($this->repoRoot()) + 1);
			}
		}
		sort($found);
		return $found;
	}

	/** Runs one copy in its own process and returns what each case left behind. */
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
			$names = array("1.bin", "2.bin", "3.bin");
			$out = array();

			// The positive control: the same three names, none of them taken.
			$src = $root . "/control/src";
			$dst = $root . "/control/dst";
			foreach ($names as $n) put($src . "/" . $n, "download " . $n);
			$ok = rtOpFiles($names, $src, $dst, "Move");
			$out["control"] = array("ret" => $ok,
				"arrived" => array_map(function($n) use ($dst) { return is_file($dst . "/" . $n); }, $names),
				"left" => array_map(function($n) use ($src) { return is_file($src . "/" . $n); }, $names));

			// One name of the three is taken, at each position in turn, for
			// every operation. Nothing may have been carried across.
			foreach (array("Move", "Copy", "HardLink", "SoftLink") as $op)
			{
				foreach (array(0, 1, 2) as $blocked)
				{
					$key = $op . ":" . $blocked;
					$src = $root . "/" . $op . $blocked . "/src";
					$dst = $root . "/" . $op . $blocked . "/dst";
					foreach ($names as $n) put($src . "/" . $n, "download " . $n);
					put($dst . "/" . $names[$blocked], "already there");
					$ok = rtOpFiles($names, $src, $dst, $op);
					$out[$key] = array("ret" => $ok,
						"occupant" => @file_get_contents($dst . "/" . $names[$blocked]),
						// every name the operation must not have created, and
						// every source file it must not have taken away
						"created" => array_values(array_filter($names,
							function($n) use ($dst, $names, $blocked) {
								return $n !== $names[$blocked] && file_exists($dst . "/" . $n);
							})),
						"missing" => array_values(array_filter($names,
							function($n) use ($src) { return !is_file($src . "/" . $n); })));
				}
			}
			// A destination that cannot be written to. Every operation fails
			// at its last step, and one that does not look at whether it
			// failed reports a success that put nothing anywhere.
			$out["unwritable"] = array();
			$canary = $root . "/unwritable/canary";
			@mkdir($canary, 0777, true);
			@chmod($canary, 0555);
			$out["unwritable"]["precondition"] =
				(@file_put_contents($canary . "/w", "x") === false);
			@chmod($canary, 0755);
			foreach (array("Move", "Copy", "HardLink", "SoftLink") as $op)
			{
				$src = $root . "/unwritable/" . $op . "/src";
				$dst = $root . "/unwritable/" . $op . "/dst";
				put($src . "/x.bin", "the download");
				@mkdir($dst, 0777, true);
				@chmod($dst, 0555);
				$ok = rtOpFiles(array("x.bin"), $src, $dst, $op);
				$out["unwritable"][$op] = array("ret" => $ok,
					"arrived" => (file_exists($dst . "/x.bin") || is_link($dst . "/x.bin")));
				// put back, or the tree cannot be cleaned up afterwards
				@chmod($dst, 0755);
			}

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

	/** A scan that finds nothing is a false green, not a pass. */
	public function testTheScanFindsTheCopiesThatShip()
	{
		$sources = $this->sources();
		$this->assertTrue(count($sources) >= 2,
			'the tree ships more than one rtOpFiles(); found: ' . implode(', ', $sources));
	}

	public function testEveryCopyStillMovesAListWithNoCollisionInIt()
	{
		foreach ($this->sources() as $source)
		{
			$r = $this->drive($source);
			$this->assertTrue($r['control']['ret'] === true,
				$source . ': three files with nothing in the way are moved');
			$this->assertEquals(array(true, true, true), $r['control']['arrived'],
				$source . ': all three arrive');
			$this->assertEquals(array(false, false, false), $r['control']['left'],
				$source . ': and none is left at the source');
		}
	}

	/**
	 * The whole point. Whichever file of the download collides, and whichever
	 * operation was asked for, a refusal has carried nothing: the source is
	 * whole and the destination holds only what was already there.
	 */
	public function testARefusalCarriesNothingWhicheverFileCollides()
	{
		foreach ($this->sources() as $source)
		{
			$r = $this->drive($source);
			foreach (array('Move', 'Copy', 'HardLink', 'SoftLink') as $op)
			{
				foreach (array(0, 1, 2) as $blocked)
				{
					$c = $r[$op . ':' . $blocked];
					$where = $source . ': ' . $op . ' blocked at file ' . ($blocked + 1) . ' of 3';
					$this->assertTrue($c['ret'] === false, $where . ' is refused');
					$this->assertEquals('already there', $c['occupant'],
						$where . ': the file that was already there survives');
					$this->assertEquals(array(), $c['created'],
						$where . ': and nothing of the download was created at the '
						. 'destination, found: ' . implode(', ', $c['created']));
					$this->assertEquals(array(), $c['missing'],
						$where . ': and the download is still whole at the source, '
						. 'missing: ' . implode(', ', $c['missing']));
				}
			}
		}
	}

	/**
	 * A copy that reports success has put the download at every destination
	 * name it was given.
	 *
	 * The operations end in one call each -- rename(), copy(), link(),
	 * symlink() -- and each can fail on its own: no permission at the
	 * destination, no space, or, for link(), a destination on a different
	 * filesystem from the source, which is EXDEV and is what a pool of fast
	 * storage beside a bulk one produces. An operation that does not look at
	 * whether its call succeeded carries on to the next file and returns true,
	 * so rtOpFiles() reports a move that never happened -- and the caller acts
	 * on it: the automove hook goes on to leave rTorrent pointing at the
	 * directory it was told the files were carried to.
	 *
	 * The assertion is the end condition and not any one branch: true means
	 * the name is there. That survives the operations being rewritten, and the
	 * scan covers a copy of rtOpFiles() that does not exist yet.
	 */
	public function testNoCopyReportsSuccessWithoutHavingCarriedTheFiles()
	{
		foreach ($this->sources() as $source)
		{
			$r = $this->drive($source);

			// A destination that turns out to be writable after all makes every
			// assertion below vacuous, so it is a failure and not a pass: run
			// the suite as an ordinary user, because root ignores the mode.
			$this->assertTrue($r['unwritable']['precondition'] === true,
				$source . ': the precondition holds -- a directory at mode 0555 '
				. 'cannot be written to, so the operations below really do fail. '
				. 'Running as a user that ignores the mode leaves this case '
				. 'unexercised rather than passing it');
			if ($r['unwritable']['precondition'] !== true)
				continue;

			foreach (array('Move', 'Copy', 'HardLink', 'SoftLink') as $op)
			{
				$c = $r['unwritable'][$op];
				$this->assertTrue($c['ret'] === false || $c['arrived'] === true,
					$source . ': ' . $op . ' into a directory it cannot write to does '
					. 'not report success -- it returned ' . var_export($c['ret'], true)
					. ' and the destination name was '
					. ($c['arrived'] ? 'made' : 'not made'));
			}
		}
	}
}
