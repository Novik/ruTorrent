<?php

require_once(__DIR__.'/TestCase.php');
require_once(__DIR__.'/ShellCommandFixture.php');
require_once(__DIR__.'/TestTree.php');
require_once(__DIR__.'/../../php/utility/lfs.php');

class ShellBytesStatProbe extends LFS
{
	public static function shellStat($path) { return parent::statPrim($path); }
}

class FilesystemShellBytesTest extends TestCase
{
	private $directory;

	public function setUp()
	{
		$this->directory = sys_get_temp_dir().'/fs-shell-'.bin2hex(random_bytes(6));
		mkdir($this->directory, 0700);
		chmod($this->directory, 0700);
	}

	public function tearDown()
	{
		TestTree::remove($this->directory);
	}

	public function testFileChecksPreserveTheActualByteName()
	{
		$path = $this->directory."/raw\xFF 'name'";
		file_put_contents($path, 'bytes');
		$this->assertTrue(LFS::test($path, 'f'), 'the real shell finds the non-UTF-8 file');
		$this->assertTrue(LFS::test($path, 'r'), 'the real shell checks that exact readable file');
		unlink($path);
		file_put_contents($this->directory."/raw 'name'", 'different');
		$this->assertTrue(!LFS::test($path, 'f'), 'a byte-stripped sibling cannot satisfy the check');
	}

	public function testExternalStatReturnsTheActualFileIdentity()
	{
		$path = $this->directory."/raw\xFF 'name'";
		file_put_contents($path, 'correct');
		file_put_contents($this->directory."/raw 'name'", 'wrong sibling');
		$actual = ShellBytesStatProbe::shellStat($path);
		$expected = stat($path);
		$this->assertTrue(is_array($actual), 'external stat succeeds for the raw-byte path');
		$this->assertTrue($actual['ino'] === $expected['ino'], 'the measured inode is the requested file');
		$this->assertTrue($actual['size'] === (float)$expected['size'], 'the measured size belongs to that inode');
	}

	public function testLargeFileCatCommandKeepsTheRawPath()
	{
		$path = $this->directory."/raw\xFF 'name'";
		file_put_contents($path, "correct payload\0bytes");
		file_put_contents($this->directory."/raw 'name'", 'wrong sibling');
		$statement = ShellCommandFixture::statement(__DIR__.'/../../php/utility/sendfile.php', "passthru('cat '");
		ob_start();
		try { ShellCommandFixture::evaluate($statement, array('filename'=>$path)); }
		finally { $actual = ob_get_clean(); }
		$this->assertTrue($actual === "correct payload\0bytes", 'the 32-bit fallback command streams the exact named payload');
	}
}
