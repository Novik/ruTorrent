<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/../../php/utility/fileutil.php');

// env_check.php is standalone by design -- it never requires the runtime, so it
// carries its own copy of the log path rules. Load only the class; the constant
// stops the checker from running its live probing.
define('RUTORRENT_REQUIREMENTS_LIB', true);
require_once(__DIR__ . '/../../env_check.php');

/**
 * The checker and the runtime hold the same rule in two places, and the failure
 * that costs an installer a day is the one where they disagree: env_check says
 * the log file is fine and toLog() then refuses to write it, or the other way
 * round. Neither copy can be shared -- the checker must run with nothing loaded
 * -- so what is pinned here is that they answer alike.
 *
 * The runtime's own helpers are private, which is right: what a caller can
 * observe is whether the line was written, so that is what is compared.
 */
class LogPathAgreementTest extends TestCase
{
	private $root;
	private $refused;

	public function setUp()
	{
		$this->root = sys_get_temp_dir() . '/rutorrent-log-path-agreement-' . getmypid();
		$this->wipe();
		mkdir($this->root, 0777, true);
	}

	public function tearDown()
	{
		$this->wipe();
	}

	private function wipe()
	{
		if (!is_dir($this->root)) {
			return;
		}
		foreach (array_diff(scandir($this->root), array('.', '..')) as $entry) {
			$path = $this->root . '/' . $entry;
			is_dir($path) ? rmdir($path) : unlink($path);
		}
		rmdir($this->root);
	}

	/**
	 * True when toLog() accepted the path as one it could write to -- that is,
	 * it did not bail out with the "must be an absolute path" warning.
	 */
	private function runtimeAccepts($path)
	{
		global $log_file, $profileMask;
		$log_file = $path;
		$profileMask = 0777;

		$this->refused = false;
		set_error_handler(function ($errno, $errstr) {
			if (strpos($errstr, 'absolute path') !== false) {
				$this->refused = true;
			}
			return true;
		});
		FileUtil::toLog('agreement probe');
		restore_error_handler();

		return !$this->refused;
	}

	/** One table of paths, both verdicts, asserted equal. */
	public function testTheCheckerAndTheRuntimeAgreeOnEveryShapeOfLogPath()
	{
		$abs = $this->root . '/log.txt';
		$paths = array(
			'an absolute path'            => $abs,
			'a bare relative path'        => 'errors.log',
			'a dotted relative path'      => './errors.log',
			'a parent-relative path'      => '../errors.log',
			'a file URI'                  => 'file://' . $abs,
			'a localhost file URI'        => 'file://localhost' . $abs,
			'a relative file URI'         => 'file://errors.log',
			'a file URI with a query'     => 'file://' . $abs . '?x=1',
			'a remote-host file URI'      => 'file://elsewhere' . $abs,
		);

		foreach ($paths as $what => $path) {
			$checker = Requirements::logFilePathValid($path);
			$runtime = $this->runtimeAccepts($path);
			$this->assertEquals($checker, $runtime,
				'env_check and toLog() agree about ' . $what . ': ' . var_export($path, true));
		}
	}

	/** The one both must accept, and the log line really lands. */
	public function testAnAbsolutePathIsAcceptedByBothAndIsWritten()
	{
		$abs = $this->root . '/written.txt';
		$this->assertTrue(Requirements::logFilePathValid($abs), 'the checker accepts an absolute path');
		$this->assertTrue($this->runtimeAccepts($abs), 'and so does toLog()');
		$this->assertTrue(is_file($abs), 'and the line was written');
		$this->assertTrue(strpos(file_get_contents($abs), 'agreement probe') !== false,
			'and it is the line that was asked for');
	}

	/**
	 * An empty $log_file is not a path that failed to validate, it is logging
	 * turned off -- config.php offers it as such -- so toLog() returns before
	 * it reaches the rules, and env_check never asks about it.
	 */
	public function testAnEmptyLogFileIsLoggingOffRatherThanABadPath()
	{
		$before = scandir($this->root);
		$this->assertTrue($this->runtimeAccepts(''),
			'an empty $log_file raises no complaint about the path');
		$this->assertEquals($before, scandir($this->root),
			'and writes nothing anywhere');
	}

	/** The one both must refuse, and nothing is written anywhere. */
	public function testARelativePathIsRefusedByBothAndWritesNothing()
	{
		$before = scandir($this->root);
		$this->assertTrue(Requirements::logFilePathValid('errors.log') === false,
			'the checker refuses a relative path');
		$this->assertTrue($this->runtimeAccepts('errors.log') === false,
			'and so does toLog()');
		$this->assertEquals($before, scandir($this->root), 'and no file appeared');
	}
}
