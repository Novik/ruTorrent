<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/utility/fileutil.php');

class TempDirectoryModeTest extends TestCase
{
	private $root;

	public function setUp()
	{
		$this->root = sys_get_temp_dir().'/rutorrent-temp-mode-'.getmypid();
		@mkdir($this->root, 0700);
	}

	public function tearDown()
	{
		global $tempDirectory, $tempDirectory_init_done, $profileMask;
		$tempDirectory = null;
		$tempDirectory_init_done = false;
		$profileMask = 0777;
		foreach(glob($this->root.'/*') ?: array() as $path)
		{
			if(is_link($path) || is_file($path)) @unlink($path);
			else if(is_dir($path))
			{
				foreach(glob($path.'/*') ?: array() as $entry) @unlink($entry);
				@rmdir($path);
			}
		}
		@rmdir($this->root);
	}

	private function mode($path)
	{
		clearstatcache(true, $path);
		return(fileperms($path) & 07777);
	}

	public function testExistingCustomTempDirectoryKeepsItsProtectedMode()
	{
		global $tempDirectory, $tempDirectory_init_done, $profileMask;
		$path = $this->root.'/existing';
		mkdir($path, 0700);
		file_put_contents($path.'/keep', 'old');
		$tempDirectory = $path;
		$tempDirectory_init_done = false;
		$profileMask = 0777;
		$this->assertEquals($path.'/', FileUtil::getTempDirectory());
		$this->assertEquals(0700, $this->mode($path),
			'selecting an existing custom temp path must not reopen its mode');
		$this->assertEquals('old', file_get_contents($path.'/keep'));
	}

	public function testSymlinkAliasToExistingCustomTempDoesNotRemodeTarget()
	{
		global $tempDirectory, $tempDirectory_init_done, $profileMask;
		$target = $this->root.'/target';
		$alias = $this->root.'/alias';
		mkdir($target, 0700);
		symlink($target, $alias);
		$tempDirectory = $alias;
		$tempDirectory_init_done = false;
		$profileMask = 0777;
		$this->assertEquals($alias.'/', FileUtil::getTempDirectory());
		$this->assertEquals(0700, $this->mode($target),
			'selecting a symlink alias must not reopen the existing target');
	}

	public function testMissingCustomTempDirectoryIsCreatedWithProfileMask()
	{
		global $tempDirectory, $tempDirectory_init_done, $profileMask;
		$path = $this->root.'/new';
		$tempDirectory = $path;
		$tempDirectory_init_done = false;
		$profileMask = 0750;
		$this->assertEquals($path.'/', FileUtil::getTempDirectory());
		$this->assertEquals(0750, $this->mode($path),
			'a missing selected temp path still uses the configured mask');
	}
}
