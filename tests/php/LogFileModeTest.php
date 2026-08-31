<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/utility/fileutil.php');

class LogFileModeStream
{
	public $context;
	public static $fileType;
	public static $exists;
	public static $metadataCalls;
	public static $openModes;
	public static $body;

	public static function reset($fileType, $exists = true)
	{
		clearstatcache(true, 'rutorrent-log-test://sink');
		self::$fileType = $fileType;
		self::$exists = $exists;
		self::$metadataCalls = array();
		self::$openModes = array();
		self::$body = '';
	}

	private static function statResult()
	{
		$mode = self::$fileType | 0600;
		$size = strlen(self::$body);
		return array(
			2 => $mode, 7 => $size,
			'mode' => $mode, 'size' => $size,
		);
	}

	public function url_stat($path, $flags)
	{
		return self::$exists ? self::statResult() : false;
	}

	public function stream_open($path, $mode, $options, &$openedPath)
	{
		self::$openModes[] = $mode;
		return true;
	}

	public function stream_write($data)
	{
		self::$body .= $data;
		return strlen($data);
	}

	public function stream_flush()
	{
		return true;
	}

	public function stream_metadata($path, $option, $value)
	{
		self::$metadataCalls[] = $option;
		return true;
	}
}

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
		if (!in_array('rutorrent-log-test', stream_get_wrappers(), true)) {
			stream_wrapper_register('rutorrent-log-test', 'LogFileModeStream');
		}
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
		if (in_array('rutorrent-log-test', stream_get_wrappers(), true)) {
			stream_wrapper_unregister('rutorrent-log-test');
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

	public function testAnExistingNonRegularLogIsNotTouchedOrReChmodded()
	{
		LogFileModeStream::reset(0010000); // FIFO
		$GLOBALS['log_file'] = 'rutorrent-log-test://sink';
		$GLOBALS['profileMask'] = 0777;
		FileUtil::toLog('probe');
		$this->assertEquals(
			array(),
			LogFileModeStream::$metadataCalls,
			'An existing non-regular log keeps its operator-assigned permissions'
		);
	}

	public function testAnExistingNonRegularFilesystemPathIsNotReChmodded()
	{
		$target = $this->dir . '/non-regular-target';
		mkdir($target, 0700);
		chmod($target, 0700);
		$GLOBALS['log_file'] = $target;
		$GLOBALS['profileMask'] = 0777;
		set_error_handler(function () { return true; });
		try {
			FileUtil::toLog('probe');
			$mode = $this->modeOf($target);
		} finally {
			restore_error_handler();
			chmod($target, 0700);
			rmdir($target);
		}
		$this->assertEquals(
			0700,
			$mode,
			'An existing non-regular filesystem target keeps its operator-assigned permissions'
		);
	}

	public function testARegularLogIsOpenedWriteOnly()
	{
		LogFileModeStream::reset(0100000); // Regular file
		$GLOBALS['log_file'] = 'rutorrent-log-test://sink';
		FileUtil::toLog('probe');
		$this->assertEquals(
			array('ab'),
			LogFileModeStream::$openModes,
			'A regular log does not require read permission just to append a line'
		);
	}

	public function testANonRegularLogKeepsTheNonBlockingReadWriteMode()
	{
		LogFileModeStream::reset(0010000); // FIFO
		$GLOBALS['log_file'] = 'rutorrent-log-test://sink';
		FileUtil::toLog('probe');
		$this->assertEquals(
			array('ab+'),
			LogFileModeStream::$openModes,
			'A non-regular log keeps the mode that does not wait forever for a FIFO reader'
		);
	}

	public function testARelativeLogPathIsRejectedBeforeItCanSplitByWorkingDirectory()
	{
		$this->clean();
		$GLOBALS['log_file'] = 'relative.log';
		$oldDirectory = getcwd();
		$warning = '';
		chdir($this->dir);
		set_error_handler(function ($number, $message) use (&$warning) {
			$warning = $message;
			return true;
		});
		try {
			FileUtil::toLog('probe');
		} finally {
			restore_error_handler();
			chdir($oldDirectory);
		}
		$this->assertTrue(
			!file_exists($this->dir . '/relative.log'),
			'A relative log path cannot create different files from different entry-point directories'
		);
		$this->assertTrue(
			strpos($warning, '$log_file must be an absolute path') !== false,
			'An invalid $log_file names the configuration error and its consequence'
		);
	}

	public function testAnUnstatableStreamSkipsFilesystemMetadataAndOpensWriteOnly()
	{
		LogFileModeStream::reset(0100000, false);
		$GLOBALS['log_file'] = 'rutorrent-log-test://sink';
		FileUtil::toLog('probe');
		$this->assertEquals(
			array(),
			LogFileModeStream::$metadataCalls,
			'A stream URI is not touched or chmodded like a filesystem path'
		);
		$this->assertEquals(
			array('ab'),
			LogFileModeStream::$openModes,
			'A stream URI only needs write access to append a line'
		);
	}

	public function testBuiltinStreamDoesNotEmitFilesystemMetadataWarnings()
	{
		$GLOBALS['log_file'] = 'php://temp';
		$warning = '';
		set_error_handler(function ($number, $message) use (&$warning) {
			$warning .= $message;
			return true;
		});
		try {
			FileUtil::toLog('probe');
		} finally {
			restore_error_handler();
		}
		$this->assertEquals('', $warning, 'A built-in stream log emits no touch or chmod warning');
	}

	public function testFileStreamHonorsTheSameMaskAsAPlainPath()
	{
		$target = $this->dir . '/file-stream.log';
		$GLOBALS['log_file'] = 'file://' . $target;
		$GLOBALS['profileMask'] = 0700;
		$oldMask = umask(0);
		try {
			FileUtil::toLog('probe');
		} finally {
			umask($oldMask);
		}
		$this->assertTrue(is_file($target), 'A file URI creates its local log target');
		$this->assertEquals(
			0600,
			$this->modeOf($target),
			'A file URI obeys the same profile mask as a plain filesystem path'
		);
	}

	public function testLocalhostFileStreamUsesAnAbsoluteLocalTarget()
	{
		if (DIRECTORY_SEPARATOR === '\\') {
			return;
		}
		$target = $this->dir . '/localhost-file-stream.log';
		$GLOBALS['log_file'] = 'file://localhost' . $target;
		$GLOBALS['profileMask'] = 0700;
		FileUtil::toLog('probe');
		$this->assertTrue(is_file($target), 'A localhost file URI writes to its absolute local target');
		$this->assertEquals(0600, $this->modeOf($target), 'A localhost file URI obeys the profile mask');
	}

	public function testRelativeFileStreamIsRejectedAtRuntime()
	{
		if (DIRECTORY_SEPARATOR === '\\') {
			return;
		}
		$GLOBALS['log_file'] = 'file://relative/path';
		$warning = '';
		set_error_handler(function ($number, $message) use (&$warning) {
			$warning = $message;
			return true;
		});
		try {
			FileUtil::toLog('probe');
		} finally {
			restore_error_handler();
		}
		$this->assertTrue(
			strpos($warning, '$log_file must be an absolute path') !== false,
			'A relative file URI is visibly rejected by the runtime parser'
		);
	}

	public function testAWindowsPathIsRelativeOnUnix()
	{
		if (DIRECTORY_SEPARATOR === '\\') {
			return;
		}
		$this->clean();
		$GLOBALS['log_file'] = 'C:\\logs\\rutorrent.log';
		$oldDirectory = getcwd();
		chdir($this->dir);
		set_error_handler(function () { return true; });
		try {
			FileUtil::toLog('probe');
		} finally {
			restore_error_handler();
			chdir($oldDirectory);
		}
		$this->assertTrue(
			!file_exists($this->dir . '/C:\\logs\\rutorrent.log'),
			'A Windows drive path cannot become a relative filename on Unix'
		);
	}
}
