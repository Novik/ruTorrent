<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/utility/fileutil.php');

/**
 * FileUtil::deleteDirectory() is ruTorrent's only recursive delete: rTask::clean()
 * empties a task directory with it, and it is public API a plugin may call on any
 * tree it owns.
 *
 * A subdirectory reached the recursive step as a bare deleteDirectory() call -- no
 * global function of that name exists anywhere in ruTorrent -- so PHP raised "Call
 * to undefined function deleteDirectory()". The @ in front of rTask::clean()'s call
 * does not suppress an Error, so the request died; and it died after scandir()'s
 * earlier entries had already been unlinked, leaving a half-deleted tree behind.
 *
 * Every case here asserts the end condition -- the tree is gone -- rather than the
 * fate of one leaf, and each is built with at least the shape that reaches the
 * recursive step.
 */
class DeleteDirectoryTest extends TestCase
{
	private $root;

	public function setUp()
	{
		$this->root = sys_get_temp_dir() . '/rutorrent-delete-directory-test-' . getmypid();
		$this->wipe();
		mkdir($this->root, 0777, true);
	}

	public function tearDown()
	{
		$this->wipe();
	}

	// Deliberately not FileUtil::deleteDirectory(): the harness must be able to
	// clean up after a run in which the method under test is the broken one.
	private function wipe($dir = null)
	{
		$dir = is_null($dir) ? $this->root : $dir;
		if (!is_dir($dir)) {
			return;
		}
		foreach (array_diff(scandir($dir), array('.', '..')) as $entry) {
			$path = $dir . '/' . $entry;
			is_dir($path) ? $this->wipe($path) : unlink($path);
		}
		rmdir($dir);
	}

	// $spec maps a path relative to the case directory to its contents; a null
	// value asks for a directory. Parent directories are created as needed.
	private function make($name, $spec)
	{
		$dir = $this->root . '/' . $name;
		mkdir($dir, 0777, true);
		foreach ($spec as $path => $contents) {
			if (is_null($contents)) {
				mkdir($dir . '/' . $path, 0777, true);
			} else {
				@mkdir(dirname($dir . '/' . $path), 0777, true);
				file_put_contents($dir . '/' . $path, $contents);
			}
		}
		return $dir;
	}

	// An undefined function is an Error, not an Exception, so TestCase::run()
	// would not catch it and the rest of the file would never run. Reporting it
	// as a failed assertion keeps every case in the file honest.
	private function deleteAndReport($dir)
	{
		try {
			return FileUtil::deleteDirectory($dir);
		} catch (Throwable $e) {
			$this->assertTrue(false, 'deleteDirectory(' . $dir . ') threw: ' . $e->getMessage());
			return null;
		}
	}

	private function assertGone($dir, $message)
	{
		clearstatcache();
		$this->assertTrue(!file_exists($dir), $message);
	}

	// The case that already worked: no recursive step is ever reached, so this
	// must keep passing.
	public function testAFlatDirectoryIsRemoved()
	{
		$dir = $this->make('flat', array('a.txt' => 'a', 'b.txt' => 'b'));
		$this->assertTrue($this->deleteAndReport($dir) === true, 'deleteDirectory() reports success on a flat directory');
		$this->assertGone($dir, 'A directory of plain files is removed');
	}

	public function testAnEmptyDirectoryIsRemoved()
	{
		$dir = $this->make('empty', array());
		$this->assertTrue($this->deleteAndReport($dir) === true, 'deleteDirectory() reports success on an empty directory');
		$this->assertGone($dir, 'An empty directory is removed');
	}

	// One subdirectory is all it takes to reach the recursive step.
	public function testASubdirectoryIsRemovedWithItsParent()
	{
		$dir = $this->make('nested', array(
			'top.txt' => 'top',
			'sub/leaf.txt' => 'leaf',
		));
		$this->assertTrue($this->deleteAndReport($dir) === true, 'deleteDirectory() reports success on a nested tree');
		$this->assertGone($dir, 'A directory containing a subdirectory is removed whole');
	}

	// An empty subdirectory has nothing to unlink, so it isolates the recursion
	// itself from the file handling around it.
	public function testAnEmptySubdirectoryIsRemoved()
	{
		$dir = $this->make('nested-empty', array('sub' => null));
		$this->assertTrue($this->deleteAndReport($dir) === true, 'deleteDirectory() reports success on an empty subdirectory');
		$this->assertGone($dir, 'An empty subdirectory does not survive its parent');
	}

	// The recursion has to hold for more than one level down.
	public function testADeepTreeIsRemoved()
	{
		$dir = $this->make('deep', array(
			'one/two/three/leaf.txt' => 'leaf',
			'one/two/sibling.txt' => 'sibling',
			'one/other/leaf.txt' => 'leaf',
		));
		$this->assertTrue($this->deleteAndReport($dir) === true, 'deleteDirectory() reports success on a deep tree');
		$this->assertGone($dir, 'Every level of a deep tree is removed');
	}

	// scandir() lists dotfiles, and only "." and ".." are filtered out, so hidden
	// entries are deleted like any other -- including a hidden subdirectory,
	// which is another way into the recursive step.
	public function testHiddenEntriesAreRemoved()
	{
		$dir = $this->make('hidden', array(
			'.hidden-file' => 'x',
			'.hidden-dir/leaf.txt' => 'leaf',
		));
		$this->assertTrue($this->deleteAndReport($dir) === true, 'deleteDirectory() reports success on hidden entries');
		$this->assertGone($dir, 'Hidden files and hidden subdirectories are removed');
	}

	// scandir() returns its entries sorted, so a subdirectory that sorts after a
	// plain file used to be reached only once that file had been unlinked. The
	// tree must not be left half-deleted whichever order the entries take.
	public function testASubdirectorySortingAfterAFileLeavesNothingBehind()
	{
		$dir = $this->make('ordering', array(
			'a-file.txt' => 'a',
			'z-dir/leaf.txt' => 'leaf',
		));
		$this->deleteAndReport($dir);
		$this->assertGone($dir, 'Nothing survives when the subdirectory sorts last');
	}

	public function testAFileSortingAfterASubdirectoryLeavesNothingBehind()
	{
		$dir = $this->make('ordering-reversed', array(
			'a-dir/leaf.txt' => 'leaf',
			'z-file.txt' => 'z',
		));
		$this->deleteAndReport($dir);
		$this->assertGone($dir, 'Nothing survives when the subdirectory sorts first');
	}

	// rTask::clean() is the in-tree caller, and it passes a path with no trailing
	// slash; deleteDirectory() adds one itself. A task directory that gained a
	// subdirectory is exactly the shape that used to kill the request.
	public function testATaskShapedDirectoryIsRemoved()
	{
		$dir = $this->make('task', array(
			'start.sh' => "#!/bin/sh\n",
			'pid' => '1',
			'status' => '0',
			'log' => '',
			'errors' => '',
			'params' => 'a:0:{}',
			'output/extracted.bin' => 'x',
		));
		$this->assertTrue($this->deleteAndReport($dir) === true, 'deleteDirectory() reports success on a task directory');
		$this->assertGone($dir, 'A task directory with a subdirectory is cleaned away');
	}

	// deleteDirectory() slashes the path itself, so both spellings must behave
	// the same.
	public function testATrailingSlashIsAccepted()
	{
		$dir = $this->make('trailing', array('sub/leaf.txt' => 'leaf'));
		$this->assertTrue($this->deleteAndReport($dir . '/') === true, 'deleteDirectory() accepts a trailing slash');
		$this->assertGone($dir, 'A path given with a trailing slash is removed whole');
	}
}
