<?php

require_once(__DIR__ . '/../../php/TestCase.php');

/**
 * Moving a download's data to another directory, at the file level.
 *
 * rtOpFiles() is what carries each file across. It used to unlink whatever
 * stood at the destination name first, so a move into a directory that already
 * held a file of that name destroyed it and then reported success -- the
 * download's own data arrived, and something else's was gone, with nothing
 * said to anyone.
 *
 * util_rt.php is copied into a tree of its own rather than included, because
 * its three require_once lines are relative to the working directory and pull
 * in the configuration and a live rtorrent. The copy is byte-compared, so what
 * runs is the shipped file.
 */
class MoveDestinationTest extends TestCase
{
	private $tree;
	private $cwd;

	public function setUp()
	{
		$this->tree = sys_get_temp_dir() . '/rutorrent-datadir-' . getmypid();
		$this->wipe();
		mkdir($this->tree . '/plugins/datadir', 0777, true);
		mkdir($this->tree . '/php', 0777, true);

		$source = dirname(dirname(dirname(__DIR__))) . '/plugins/datadir/util_rt.php';
		$target = $this->tree . '/plugins/datadir/util_rt.php';
		copy($source, $target);
		if (hash_file('sha256', $source) !== hash_file('sha256', $target))
			throw new Exception('could not copy util_rt.php');

		// Everything below this file's own boundary: the log sink, and the
		// large-file helpers it asks about the filesystem through.
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

		// The require_once lines resolve against the working directory, which
		// is what setdir.php arranges for itself with chdir().
		$this->cwd = getcwd();
		chdir($this->tree . '/plugins/datadir');
		require_once($this->tree . '/plugins/datadir/util_rt.php');
	}

	public function tearDown()
	{
		if ($this->cwd !== false)
			chdir($this->cwd);
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

	private function file($path, $content)
	{
		@mkdir(dirname($path), 0777, true);
		file_put_contents($path, $content);
		return $path;
	}

	private function src() { return $this->tree . '/src'; }
	private function dst() { return $this->tree . '/dst'; }

	// -- what must keep working ---------------------------------------------

	public function testAnOrdinaryMoveStillMovesEveryFile()
	{
		$this->file($this->src() . '/a.bin', 'AAA');
		$this->file($this->src() . '/sub/b.bin', 'BBB');
		@mkdir($this->dst(), 0777, true);

		$ok = rtOpFiles(array('a.bin', 'sub/b.bin'), $this->src(), $this->dst(), 'Move');

		$this->assertTrue($ok === true, 'the move succeeds');
		$this->assertEquals('AAA', @file_get_contents($this->dst() . '/a.bin'),
			'the file arrives with its content');
		$this->assertEquals('BBB', @file_get_contents($this->dst() . '/sub/b.bin'),
			'and so does the one in a subdirectory');
		$this->assertTrue(!is_file($this->src() . '/a.bin'),
			'and it is gone from the source');
	}

	// -- the destination is not this move's to destroy ----------------------

	public function testAFileAlreadyAtTheDestinationIsNotDestroyed()
	{
		$this->file($this->src() . '/readme.txt', 'the download being moved');
		$bystander = $this->file($this->dst() . '/readme.txt', 'someone else\'s file');

		$ok = rtOpFiles(array('readme.txt'), $this->src(), $this->dst(), 'Move');

		$this->assertTrue($ok === false, 'the move is refused');
		$this->assertEquals('someone else\'s file', file_get_contents($bystander),
			'the file that was already there is untouched');
		$this->assertEquals('the download being moved',
			@file_get_contents($this->src() . '/readme.txt'),
			'and the download\'s own file has not left the source');
	}

	public function testADirectoryAtTheDestinationNameIsNotDestroyed()
	{
		$this->file($this->src() . '/thing', 'a file');
		@mkdir($this->dst() . '/thing', 0777, true);
		$inside = $this->file($this->dst() . '/thing/kept.txt', 'kept');

		$ok = rtOpFiles(array('thing'), $this->src(), $this->dst(), 'Move');

		$this->assertTrue($ok === false, 'a directory in the way refuses the move');
		$this->assertTrue(is_file($inside), 'and what is inside it is still there');
	}

	public function testASymlinkAtTheDestinationNameIsNotFollowed()
	{
		$this->file($this->src() . '/link', 'a file');
		$target = $this->file($this->tree . '/elsewhere/target.txt', 'must survive');
		@mkdir($this->dst(), 0777, true);
		@symlink($target, $this->dst() . '/link');

		$ok = rtOpFiles(array('link'), $this->src(), $this->dst(), 'Move');

		$this->assertTrue($ok === false, 'a symlink in the way refuses the move');
		$this->assertEquals('must survive', file_get_contents($target),
			'what it pointed at is untouched');
	}

	public function testACopyDoesNotOverwriteEither()
	{
		// "Move" and "Copy" write over a destination by themselves; the unlink
		// was what let the two link operations do the same. All four are
		// refused now, so the claim is about the destination and not about one
		// operation.
		$this->file($this->src() . '/x', 'new');
		$bystander = $this->file($this->dst() . '/x', 'old');

		foreach(array('Copy', 'HardLink', 'SoftLink', 'Move') as $op)
		{
			$ok = rtOpFiles(array('x'), $this->src(), $this->dst(), $op);
			$this->assertTrue($ok === false, $op.' is refused');
			$this->assertEquals('old', file_get_contents($bystander),
				'the file already there survives '.$op);
		}
	}

	public function testTheRefusalStopsBeforeAnyLaterFileIsTouched()
	{
		// A refusal mid-list must not leave half the download at the
		// destination and half at the source without saying so; the caller
		// sees false and reports the failure.
		$this->file($this->src() . '/1.bin', 'one');
		$this->file($this->src() . '/2.bin', 'two');
		$this->file($this->dst() . '/1.bin', 'in the way');

		$ok = rtOpFiles(array('1.bin', '2.bin'), $this->src(), $this->dst(), 'Move');

		$this->assertTrue($ok === false, 'the move is refused');
		$this->assertTrue(!is_file($this->dst() . '/2.bin'),
			'the file after the refusal is not moved');
	}
}
