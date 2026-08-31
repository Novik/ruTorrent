<?php

require_once(__DIR__ . '/TestCase.php');

class EnvCheckTest extends TestCase
{
	public function testInvalidFileLogUriIsReportedWithoutACheckerDeprecation()
	{
		if (DIRECTORY_SEPARATOR === '\\') {
			return;
		}
		$checker = __DIR__ . '/../../env_check.php';
		$command = 'RU_LOG_FILE=' . escapeshellarg('file://relative/path') . ' ' .
			escapeshellarg(PHP_BINARY) .
			' -d variables_order=EGPCS -d error_reporting=E_ALL -d display_errors=1 ' .
			escapeshellarg($checker) . ' 2>&1';
		$output = array();
		exec($command, $output);
		$transcript = implode("\n", $output);
		$this->assertTrue(
			strpos($transcript, '[WARN] $log_file writable') !== false,
			'An invalid file URI is reported as a configuration warning'
		);
		$this->assertTrue(
			strpos($transcript, 'dirname(): Passing null') === false,
			'An invalid file URI does not trigger a checker deprecation'
		);
	}
}
