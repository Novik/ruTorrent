<?php

require_once(__DIR__ . '/../../php/TestCase.php');
require_once(__DIR__ . '/../../../plugins/ratio/ratio.php');

class EraseWithDataCommandTest extends TestCase
{
	private function command($force)
	{
		$rat = new rRatio();
		return($rat->getEraseWithDataCommand($force));
	}

	public function testStopsAndClosesBeforeDeleting()
	{
		$this->assertEquals(0, strpos($this->command("1"), "d.stop=; d.close=; "),
			'the download is stopped and closed first');
	}

	public function testHandsTheEraseToTheErasedataHelper()
	{
		$cmd = $this->command("1");
		$this->assertTrue(strpos($cmd, "/erasedata/erase.php") !== false,
			'the erase runs through the erasedata helper, which records the file list first');
		$this->assertTrue(strpos($cmd, "d.erase") === false,
			'the group command no longer erases by itself, or the data would be gone with the download');
	}

	public function testHelperIsShipped()
	{
		$this->assertTrue(is_file(__DIR__ . '/../../../plugins/erasedata/erase.php'),
			'the helper the command points at is part of the tree');
	}

	public function testRunsInTheBackground()
	{
		$this->assertTrue(strpos($this->command("1"), "execute.nothrow.bg={") !== false,
			'a foreground execute would block rtorrent while the helper waits for rtorrent');
	}

	public function testPassesTheHashOfTheDownloadItRunsOn()
	{
		$this->assertTrue(strpos($this->command("1"), ',$' . getCmd("d.get_hash") . '=,') !== false,
			'the hash is substituted by rtorrent when the group command fires');
	}

	public function testForceFlagDistinguishesTheTwoActions()
	{
		$hash = '$' . getCmd("d.get_hash") . '=';
		$this->assertTrue(strpos($this->command("1"), $hash . ',1,') !== false,
			'Remove data asks for the download\'s own files');
		$this->assertTrue(strpos($this->command("2"), $hash . ',2,') !== false,
			'Remove data (All) asks for the whole base path');
	}
}
