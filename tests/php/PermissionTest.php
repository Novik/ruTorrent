<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/utility/permission.php');

class PermissionTest extends TestCase
{
	protected $fixtureDir = null;
	protected $dirs = [];

	public function setUp()
	{
		$this->fixtureDir = __DIR__ . '/fixtures';
		$this
			->addDir('dir1', '/dir1')
			->addDir('dir2', '/dir2')
			->addDir('dir3', '/dir1/dir3', $this->fixtureDir . '/dir2')
			->addDir('dir4', '/dir1/dir4', '../dir2')
			->createDirs();
	}

	public function tearDown()
	{
		$this->cleanDirs();
	}

	protected function addDir($name, $dir, $symlink = null)
	{
		$this->dirs[$name] = [$this->fixtureDir . $dir, $symlink];

		return $this;
	}

	protected function getDir($name)
	{
		return isset($this->dirs[$name]) ? $this->dirs[$name][0] : null;
	}

	protected function createDirs()
	{
		foreach ($this->dirs as [$dir, $symlink]) {
			if ($symlink) {
				if (is_link($dir)) {
					unlink($dir);
				}
				symlink($symlink, $dir);
			} else {
				if (!is_dir($dir)) {
					mkdir($dir, 0777, true);
				}
			}
		}

		return $this;
	}

	protected function cleanDirs()
	{
		foreach (array_reverse($this->dirs) as [$dir, $symlink]) {
			if ($symlink) {
				if (is_link($dir)) {
					unlink($dir);
				}
			} else {
				if (is_dir($dir)) {
					rmdir($dir);
				}
			}
		}

		return $this;
	}

	public function testPermission()
	{
		// doesUserHave() answers "would this uid/gids have rwx on the file" from
		// the file's stat. Probe with the running process's own uid/gids -- it
		// owns the fixtures -- rather than a hardcoded 1000, so the test does not
		// depend on which uid runs it.
		$uid = posix_getuid();
		$gids = array_merge([posix_getgid()], posix_getgroups());
		$this->assertTrue(Permission::doesUserHave($uid, $gids, $this->getDir('dir1'), 0x0007), 'User has permission for directory');
		$this->assertTrue(Permission::doesUserHave($uid, $gids, $this->getDir('dir3'), 0x0007), 'User has permission for symlinked directory');
		$this->assertTrue(Permission::doesUserHave($uid, $gids, $this->getDir('dir4'), 0x0007), 'User has permission for relative symlinked directory');
	}
}
