<?php

require_once(__DIR__ . '/TestCase.php');

class TestCaseTest extends TestCase
{
	public function testFalseValueIsReportedAsFailureWhenAssertionsAreDisabled(): void
	{
		// The suite deliberately enables assertions, so use a fresh process to
		// prove that TestCase itself does not depend on that runner setting.
		$script = 'require ' . var_export(__DIR__ . '/TestCase.php', true)
			. '; (new TestCase())->assertTrue(false, "deliberately false");';
		$lines = array();
		$exitCode = 0;
		exec(escapeshellarg(PHP_BINARY) . ' -d zend.assertions=-1 -r '
			. escapeshellarg($script), $lines, $exitCode);
		$output = implode("\n", $lines) . (count($lines) ? "\n" : '');

		if ($exitCode !== 0 || $output !== "Failed: deliberately false\n") {
			throw new RuntimeException('A false assertion was not reported as Failed');
		}
		echo "Passed: a false value is reported as Failed when assertions are disabled\n";
	}

	public function testRunnerUsesStrictDiagnosticSettings(): void
	{
		$actual = array(
			'zend.assertions' => (int) ini_get('zend.assertions'),
			'error_reporting' => error_reporting(),
			'display_errors' => (int) ini_get('display_errors'),
		);
		$expected = array(
			'zend.assertions' => 1,
			'error_reporting' => -1,
			'display_errors' => 1,
		);

		if ($actual !== $expected) {
			throw new RuntimeException(
				'Test runner diagnostics differ: ' . json_encode($actual)
			);
		}
		echo "Passed: the runner enables strict diagnostics\n";
	}
}
