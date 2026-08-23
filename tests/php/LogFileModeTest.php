<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/utility/fileutil.php');

/**
 * FileUtil::toLog() creates $log_file on first write. $profileMask is the only
 * knob an operator has for saying how permissive ruTorrent may be when it
 * creates a file that a second user may also have to write -- config.php
 * documents 0770 for "Webserver and rtorrent users are in the same group" --
 * and FileUtil::makeDirectory(), the method directly above toLog() in this same
 * class, already reads it. The log did not, so an operator who tightened the
 * mask got every profile artifact tightened and the log left at 0666.
 *
 * The mask is an upper bound, never a floor: at the 0777 default 0777 & 0666 is
 * 0666, so a default install is byte-for-byte unchanged and an rtorrent user
 * distinct from the web user keeps the write access the mode exists for.
 */
class LogFileModeTest extends TestCase
{
	private $dir;

	public function setUp()
	{
		$this->dir = sys_get_temp_dir() . '/rutorrent-log-file-mode-test-' . getmypid();
		if (!is_dir($this->dir)) {
			mkdir($this->dir, 0777, true);
		}
		$this->clean();
	}

	public function tearDown()
	{
		$this->clean();
		if (is_dir($this->dir)) {
			rmdir($this->dir);
		}
	}

	private function clean()
	{
		foreach (glob($this->dir . '/*') as $path) {
			unlink($path);
		}
	}

	// toLog() only creates and chmods when the file is absent, so every case
	// starts from a path that does not exist yet.
	private function logAfterFirstWrite($mask, $name)
	{
		$this->clean();
		$GLOBALS['log_file'] = $this->dir . '/' . $name;
		if (is_null($mask)) {
			unset($GLOBALS['profileMask']);
		} else {
			$GLOBALS['profileMask'] = $mask;
		}
		FileUtil::toLog('probe');
		clearstatcache();
		return $GLOBALS['log_file'];
	}

	private function modeOf($file)
	{
		clearstatcache(true, $file);
		return fileperms($file) & 0777;
	}

	// Passes both before and after the change: the default must not move, or
	// every install where rtorrent runs as its own user loses its log.
	public function testDefaultMaskStillCreatesTheLogWorldWritable()
	{
		$log = $this->logAfterFirstWrite(0777, 'default.log');
		$this->assertTrue(is_file($log), 'toLog() creates the log file');
		$this->assertEquals(
			0666,
			$this->modeOf($log),
			'At the 0777 default the log is created 0666, exactly as before'
		);
	}

	// config.php's own worked example: web server and rtorrent share a group.
	public function testSharedGroupMaskDropsWorldAccess()
	{
		$log = $this->logAfterFirstWrite(0770, 'shared-group.log');
		$this->assertEquals(
			0660,
			$this->modeOf($log),
			'A 0770 mask creates the log 0660, so the group still writes it and the world does not'
		);
	}

	// One user for both roles: nothing outside it needs the log at all.
	public function testSingleUserMaskKeepsTheLogPrivate()
	{
		$log = $this->logAfterFirstWrite(0700, 'single-user.log');
		$this->assertEquals(
			0600,
			$this->modeOf($log),
			'A 0700 mask creates the log 0600'
		);
	}

	// The mask only ever removes bits. A mask with no write bits of its own
	// must not hand the log write bits back.
	public function testMaskIsAnUpperBoundNotAFloor()
	{
		$log = $this->logAfterFirstWrite(0444, 'read-only.log');
		$this->assertEquals(
			0444,
			$this->modeOf($log),
			'A 0444 mask cannot be widened to 0666'
		);
	}

	// toLog() may be reached before conf/config.php has been loaded, so an
	// unset mask must behave like the documented 0777 default rather than
	// chmod'ing the log to 0.
	public function testUnsetMaskFallsBackToTheDocumentedDefault()
	{
		$log = $this->logAfterFirstWrite(null, 'no-mask.log');
		$this->assertEquals(
			0666,
			$this->modeOf($log),
			'With no $profileMask set the log is created 0666'
		);
	}

	// Creation is the only moment toLog() sets a mode. An operator who chmods
	// an existing log keeps that mode.
	public function testAnExistingLogIsNotReChmodded()
	{
		$log = $this->logAfterFirstWrite(0777, 'existing.log');
		chmod($log, 0640);
		$GLOBALS['profileMask'] = 0700;
		FileUtil::toLog('second line');
		$this->assertEquals(
			0640,
			$this->modeOf($log),
			'An existing log keeps the mode it already had'
		);
	}

	// Whatever the mode, the log must still be a log.
	public function testTheLineIsStillWritten()
	{
		$log = $this->logAfterFirstWrite(0700, 'content.log');
		FileUtil::toLog('second line');
		$body = file_get_contents($log);
		$this->assertTrue(
			strpos($body, 'probe') !== false && strpos($body, 'second line') !== false,
			'Both lines are appended to the log'
		);
	}
}
