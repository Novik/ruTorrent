<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/utility/fileutil.php');

/**
 * FileUtil::makeDirectory() is how ruTorrent creates the directories it needs --
 * a profile, a task directory, a download target -- and it takes either one path
 * or a list of them. It also stands in for mkdir -p on a path that already
 * exists, where it must adjust the mode and leave what is there alone.
 *
 * Its two branches were written without braces, one of them wrapping a foreach,
 * which is a shape a reader has to count indentation to resolve. These pin what
 * it does so bracing it cannot change it.
 */
class MakeDirectoryTest extends TestCase
{
	private $root;

	public function setUp()
	{
		$this->root = sys_get_temp_dir() . '/rutorrent-make-directory-test-' . getmypid();
		$this->wipe();
		mkdir($this->root, 0777, true);
	}

	public function tearDown()
	{
		$this->wipe();
	}

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

	private function mode($path)
	{
		clearstatcache(true, $path);
		return fileperms($path) & 0777;
	}

	/** One path, given as a string. */
	public function testASinglePathIsCreated()
	{
		$dir = $this->root . '/single';
		FileUtil::makeDirectory($dir, 0755);
		$this->assertTrue(is_dir($dir), 'the directory named by a string is created');
		$this->assertEquals(0755, $this->mode($dir), 'and it carries the mode it was given');
	}

	/** Every path of a list, which is the branch that wraps a foreach. */
	public function testEveryPathOfAListIsCreated()
	{
		$dirs = array(
			$this->root . '/list-a',
			$this->root . '/list-b',
			$this->root . '/list-c',
		);
		FileUtil::makeDirectory($dirs, 0755);
		foreach ($dirs as $dir) {
			$this->assertTrue(is_dir($dir), 'every directory of the list is created: ' . basename($dir));
		}
	}

	/** Missing parents are created too -- the mkdir is recursive. */
	public function testAPathIsCreatedWithItsParents()
	{
		$dir = $this->root . '/deep/deeper/deepest';
		FileUtil::makeDirectory($dir, 0755);
		$this->assertTrue(is_dir($dir), 'a path several levels down is created');
		$this->assertTrue(is_dir($this->root . '/deep'), 'and so are the levels above it');
	}

	/**
	 * A directory that is already there keeps what it holds. This is the half of
	 * the branch that chmods instead of creating, and the one a caller relies on
	 * when it asks for a directory it may already own.
	 */
	public function testAnExistingDirectoryIsKeptAndReModed()
	{
		$dir = $this->root . '/existing';
		mkdir($dir, 0700, true);
		file_put_contents($dir . '/keep.txt', 'still here');

		FileUtil::makeDirectory($dir, 0755);

		$this->assertTrue(is_dir($dir), 'the directory is still a directory');
		$this->assertEquals('still here', file_get_contents($dir . '/keep.txt'),
			'and what it held is untouched');
		$this->assertEquals(0755, $this->mode($dir), 'the mode is the one just asked for');
	}

	/** A list may mix directories that exist with ones that do not. */
	public function testAListMayMixExistingAndMissingPaths()
	{
		$there = $this->root . '/mixed-there';
		$missing = $this->root . '/mixed-missing';
		mkdir($there, 0700, true);
		file_put_contents($there . '/keep.txt', 'still here');

		FileUtil::makeDirectory(array($there, $missing), 0755);

		$this->assertEquals('still here', file_get_contents($there . '/keep.txt'),
			'the one that existed keeps its contents');
		$this->assertEquals(0755, $this->mode($there), 'and takes the new mode');
		$this->assertTrue(is_dir($missing), 'the one that did not exist is created');
	}

	/** An empty list asks for nothing and must do nothing. */
	public function testAnEmptyListCreatesNothing()
	{
		// Its own directory: setUp() runs once for the file, not once per
		// test, so the root carries whatever the tests above it made.
		$scope = $this->root . '/empty-list';
		mkdir($scope, 0777, true);

		FileUtil::makeDirectory(array(), 0755);

		$this->assertEquals(array('.', '..'), scandir($scope),
			'an empty list leaves the filesystem alone');
	}
}
