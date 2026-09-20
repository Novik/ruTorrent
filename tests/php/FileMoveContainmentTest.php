<?php

require_once(__DIR__ . '/TestCase.php');

/**
 * A move stays inside the directory it was given, and a move that stops part
 * way through puts back what it had already carried.
 *
 * rtOpFiles() is handed a destination directory and a list of paths relative
 * to it, and the paths come from the torrent -- from elements its publisher
 * chose. It ends in mkdir(), rename(), copy(), link() and symlink(), and every
 * one of those follows a symlink in the middle of the path it is given. A
 * directory under the destination that is a symlink is therefore a name inside
 * the download deciding where outside the download the files land, and for a
 * move the source is unlinked afterwards, so the download is gone from where
 * it was and is somewhere nobody asked for.
 *
 * The same walk is where the download can be split. Names are settled before
 * the first file is carried, but the check that settles them can only see the
 * name itself: a later file whose parent is a regular file reads as free,
 * because there is no way down to the leaf to look at. The walk then carries
 * the earlier files and fails at that parent, returning false with the
 * download in two directories -- and every caller reads false as "the
 * operation did not happen". A collision or an I/O error arriving after the
 * check has the same shape.
 *
 * Both are covered here for every operation mode and for every copy of
 * rtOpFiles() in the tree, found by scanning rather than by naming them, so a
 * third copy -- a plugin vendoring util_rt.php, which is how the second one
 * came about -- is covered the day it is added.
 *
 * Each copy is driven in a process of its own: they define the same function
 * names, so one process cannot hold two. The copy is byte compared after
 * copying, so what runs is the shipped file, and a destination that is itself
 * a symlink is moved into as well, so a probe that refused everything fails
 * instead of reading as a row of successful refusals.
 */
class FileMoveContainmentTest extends TestCase
{
	private $tree;
	private $driven = array();

	public function setUp()
	{
		$this->tree = sys_get_temp_dir() . '/rutorrent-movecontain-' . getmypid();
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
		// Modes are put back first: a case leaves a directory unwritable on
		// purpose, and nothing under it can be removed until it is writable.
		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($this->tree, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($items as $item)
			if ($item->isDir() && !$item->isLink())
				@chmod($item->getPathname(), 0755);
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
		if (isset($this->driven[$source]))
			return $this->driven[$source];

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
				public static function toLog($msg) {}
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
			function there($p) { return(is_link($p) || file_exists($p)); }
			$ops = array("Move", "Copy", "HardLink", "SoftLink");
			$out = array();

			// The positive control, and the layout this must not break: the
			// destination is itself a symlink into somewhere else, which is
			// how a download directory pointing at a storage pool looks. The
			// destination is what the caller said it is, so it is followed --
			// and everything is measured from where it lands.
			foreach ($ops as $op)
			{
				$b = $root . "/control/" . $op;
				$src = $b . "/src"; $pool = $b . "/pool"; $dst = $b . "/dst";
				put($src . "/1.bin", "one");
				put($src . "/sub/2.bin", "two");
				@mkdir($pool, 0777, true);
				symlink($pool, $dst);
				$ok = rtOpFiles(array("1.bin", "sub/2.bin"), $src, $dst, $op);
				$out["control"][$op] = array("ret" => $ok,
					"arrived" => there($pool . "/1.bin") && there($pool . "/sub/2.bin"));
			}

			// A destination whose own directory is not there yet, several
			// levels below anything that is. None of it exists, so none of it
			// can be a symlink, and the caller is entitled to be told its
			// names are free.
			foreach ($ops as $op)
			{
				$b = $root . "/fresh/" . $op;
				$src = $b . "/src"; $dst = $b . "/dst/deep/deeper";
				put($src . "/1.bin", "one");
				put($src . "/sub/2.bin", "two");
				$out["fresh"][$op] = array(
					"taken" => rtTakenDestination(array("1.bin", "sub/2.bin"), $dst),
					"ret" => rtOpFiles(array("1.bin", "sub/2.bin"), $src, $dst, $op),
					"arrived" => there($dst . "/1.bin") && there($dst . "/sub/2.bin"));
			}

			// A directory between the destination and a file of the download
			// is a symlink. Nothing may be created where it points, and for a
			// move nothing may be taken away from the source.
			foreach ($ops as $op)
			{
				$b = $root . "/symlinkparent/" . $op;
				$src = $b . "/src"; $dst = $b . "/dst"; $outside = $b . "/outside";
				put($src . "/sub/payload.bin", "the download");
				@mkdir($dst, 0777, true);
				put($outside . "/neighbour.bin", "somebody else\'s");
				symlink($outside, $dst . "/sub");
				$ok = rtOpFiles(array("sub/payload.bin"), $src, $dst, $op);
				$out["symlinkparent"][$op] = array("ret" => $ok,
					"escaped" => there($outside . "/payload.bin"),
					"neighbour" => @file_get_contents($outside . "/neighbour.bin"),
					"source" => is_file($src . "/sub/payload.bin"));
			}

			// The parent of the second file is a regular file. There is no way
			// down to the leaf, so the leaf reads as free however it is asked
			// about -- and the first file must not have been carried by the
			// time that is discovered.
			foreach ($ops as $op)
			{
				$b = $root . "/fileparent/" . $op;
				$src = $b . "/src"; $dst = $b . "/dst";
				put($src . "/1.bin", "one");
				put($src . "/sub/2.bin", "two");
				put($dst . "/sub", "a regular file standing where a directory must go");
				$ok = rtOpFiles(array("1.bin", "sub/2.bin"), $src, $dst, $op);
				$out["fileparent"][$op] = array("ret" => $ok,
					"occupant" => @file_get_contents($dst . "/sub"),
					"created" => there($dst . "/1.bin"),
					"source" => is_file($src . "/1.bin") && is_file($src . "/sub/2.bin"));
			}

			// A name free when the list was checked and taken by the time the
			// walk reaches it. The list names it twice, so the operation is
			// the one that takes it: the same window an unrelated download
			// arriving in the directory opens, without the timing.
			foreach ($ops as $op)
			{
				$b = $root . "/latecollision/" . $op;
				$src = $b . "/src"; $dst = $b . "/dst";
				put($src . "/1.bin", "one");
				@mkdir($dst, 0777, true);
				$ok = rtOpFiles(array("1.bin", "1.bin"), $src, $dst, $op);
				$out["latecollision"][$op] = array("ret" => $ok,
					"created" => there($dst . "/1.bin"),
					"source" => is_file($src . "/1.bin"));
			}

			// The second file is not at the source at all -- a list drawn up
			// before the download was touched by something else.
			foreach ($ops as $op)
			{
				$b = $root . "/sourcemissing/" . $op;
				$src = $b . "/src"; $dst = $b . "/dst";
				put($src . "/1.bin", "one");
				@mkdir($dst, 0777, true);
				$ok = rtOpFiles(array("1.bin", "2.bin"), $src, $dst, $op);
				$out["sourcemissing"][$op] = array("ret" => $ok,
					"all" => there($dst . "/1.bin") && there($dst . "/2.bin"),
					"created" => there($dst . "/1.bin"),
					"source" => is_file($src . "/1.bin"));
			}

			// The second file fails in the operation itself, after the first
			// one has been carried: its parent is a real directory, so the
			// walk goes down into it, and it cannot be written to.
			$canary = $root . "/midfailure/canary";
			@mkdir($canary, 0777, true);
			@chmod($canary, 0555);
			$out["midfailure"]["precondition"] =
				(@file_put_contents($canary . "/w", "x") === false);
			@chmod($canary, 0755);
			foreach ($ops as $op)
			{
				$b = $root . "/midfailure/" . $op;
				$src = $b . "/src"; $dst = $b . "/dst";
				put($src . "/1.bin", "one");
				put($src . "/sub/2.bin", "two");
				@mkdir($dst . "/sub", 0777, true);
				@chmod($dst . "/sub", 0555);
				$ok = rtOpFiles(array("1.bin", "sub/2.bin"), $src, $dst, $op);
				$out["midfailure"][$op] = array("ret" => $ok,
					"created" => there($dst . "/1.bin"),
					"source" => is_file($src . "/1.bin") && is_file($src . "/sub/2.bin"));
				@chmod($dst . "/sub", 0755);
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
		$this->driven[$source] = $result;
		return $result;
	}

	/** A scan that finds nothing is a false green, not a pass. */
	public function testTheScanFindsTheCopiesThatShip()
	{
		$sources = $this->sources();
		$this->assertTrue(count($sources) >= 2,
			'the tree ships more than one rtOpFiles(); found: ' . implode(', ', $sources));
	}

	/**
	 * The control. A destination that is itself a symlink is the caller's own
	 * destination and is followed, so the ordinary seedbox layout still works
	 * and the refusals below mean something.
	 */
	public function testADestinationThatIsItselfASymlinkIsStillMovedInto()
	{
		foreach ($this->sources() as $source)
		{
			$r = $this->drive($source);
			foreach (array('Move', 'Copy', 'HardLink', 'SoftLink') as $op)
			{
				$c = $r['control'][$op];
				$this->assertTrue($c['ret'] === true,
					$source . ': ' . $op . ' into a destination that is a symlink is carried out');
				$this->assertTrue($c['arrived'] === true,
					$source . ': ' . $op . ' put both files where the destination points');
			}
		}
	}

	/**
	 * The other control, and the one the callers outside rtOpFiles() depend
	 * on: rtTakenDestination() answers for a destination directory that has
	 * not been created yet. The panel asks it before offering to move, so
	 * reporting a name as taken because nothing around it exists refuses a
	 * destination the user is entitled to.
	 */
	public function testNamesUnderADestinationNotYetCreatedAreFree()
	{
		foreach ($this->sources() as $source)
		{
			$r = $this->drive($source);
			foreach (array('Move', 'Copy', 'HardLink', 'SoftLink') as $op)
			{
				$c = $r['fresh'][$op];
				$this->assertEquals('', $c['taken'],
					$source . ': ' . $op . ' -- nothing is taken under a destination that '
					. 'does not exist yet; it answered ' . var_export($c['taken'], true));
				$this->assertTrue($c['ret'] === true,
					$source . ': ' . $op . ' into a destination that does not exist yet is carried out');
				$this->assertTrue($c['arrived'] === true,
					$source . ': ' . $op . ' put both files there');
			}
		}
	}

	/**
	 * The containment itself: a symlinked directory under the destination is
	 * not walked through.
	 */
	public function testNothingIsWrittenThroughASymlinkedDirectory()
	{
		foreach ($this->sources() as $source)
		{
			$r = $this->drive($source);
			foreach (array('Move', 'Copy', 'HardLink', 'SoftLink') as $op)
			{
				$c = $r['symlinkparent'][$op];
				$where = $source . ': ' . $op . ' through a symlinked directory';
				$this->assertTrue($c['ret'] === false, $where . ' is refused');
				$this->assertTrue($c['escaped'] === false,
					$where . ' created nothing where the symlink points');
				$this->assertEquals('somebody else\'s', $c['neighbour'],
					$where . ' left what is there alone');
				$this->assertTrue($c['source'] === true,
					$where . ' left the download at the source');
			}
		}
	}

	/**
	 * The split: a parent that is a regular file is discovered before anything
	 * is carried, and if it is discovered later the earlier files go back.
	 */
	public function testAParentThatIsARegularFileDoesNotSplitTheDownload()
	{
		foreach ($this->sources() as $source)
		{
			$r = $this->drive($source);
			foreach (array('Move', 'Copy', 'HardLink', 'SoftLink') as $op)
			{
				$c = $r['fileparent'][$op];
				$where = $source . ': ' . $op . ' with the second file\'s parent a regular file';
				$this->assertTrue($c['ret'] === false, $where . ' is refused');
				$this->assertEquals('a regular file standing where a directory must go',
					$c['occupant'], $where . ' left the file that was in the way alone');
				$this->assertTrue($c['created'] === false,
					$where . ' left none of the download at the destination');
				$this->assertTrue($c['source'] === true,
					$where . ' left the download whole at the source');
			}
		}
	}

	/** A name taken after the list was checked, and before the walk reaches it. */
	public function testANameTakenAfterTheCheckDoesNotSplitTheDownload()
	{
		foreach ($this->sources() as $source)
		{
			$r = $this->drive($source);
			foreach (array('Move', 'Copy', 'HardLink', 'SoftLink') as $op)
			{
				$c = $r['latecollision'][$op];
				$where = $source . ': ' . $op . ' onto a name taken after the check';
				$this->assertTrue($c['ret'] === false, $where . ' is refused');
				$this->assertTrue($c['created'] === false,
					$where . ' left nothing behind at the destination');
				$this->assertTrue($c['source'] === true,
					$where . ' left the download at the source');
			}
		}
	}

	/**
	 * A file of the list that is not at the source. Either every name is made
	 * -- a soft link to something that is not there yet is still a link the
	 * caller asked for -- or the operation is refused and has carried nothing.
	 */
	public function testAMissingSourceFileDoesNotSplitTheDownload()
	{
		foreach ($this->sources() as $source)
		{
			$r = $this->drive($source);
			foreach (array('Move', 'Copy', 'HardLink', 'SoftLink') as $op)
			{
				$c = $r['sourcemissing'][$op];
				$where = $source . ': ' . $op . ' with the second file missing at the source';
				$this->assertTrue($c['ret'] === false || $c['all'] === true,
					$where . ' does not report success without having made every name');
				if ($c['ret'] !== false)
					continue;
				$this->assertTrue($c['created'] === false,
					$where . ' left nothing of the download at the destination');
				$this->assertTrue($c['source'] === true,
					$where . ' left what was at the source where it was');
			}
		}
	}

	/**
	 * A failure in the operation itself, after an earlier file has already
	 * been carried. This is the case no check before the walk can catch, so it
	 * is the one that says whether the walk undoes itself.
	 */
	public function testAFailureInTheMiddleOfTheWalkIsUndone()
	{
		foreach ($this->sources() as $source)
		{
			$r = $this->drive($source);

			// A directory that turns out to be writable after all makes every
			// assertion below vacuous, so it is a failure and not a pass: run
			// the suite as an ordinary user, because root ignores the mode.
			$this->assertTrue($r['midfailure']['precondition'] === true,
				$source . ': the precondition holds -- a directory at mode 0555 '
				. 'cannot be written to, so the second file really does fail. '
				. 'Running as a user that ignores the mode leaves this case '
				. 'unexercised rather than passing it');
			if ($r['midfailure']['precondition'] !== true)
				continue;

			foreach (array('Move', 'Copy', 'HardLink', 'SoftLink') as $op)
			{
				$c = $r['midfailure'][$op];
				$where = $source . ': ' . $op . ' failing on the second file';
				$this->assertTrue($c['ret'] === false, $where . ' reports failure');
				$this->assertTrue($c['created'] === false,
					$where . ' took the first file back off the destination');
				$this->assertTrue($c['source'] === true,
					$where . ' left the download whole at the source');
			}
		}
	}
}
