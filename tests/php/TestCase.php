<?php

class TestCase
{
	private $failures = 0;

	public function failureCount(): int
	{
		return $this->failures;
	}

	private function runnableMethods(): array
	{
		return array_values(array_filter(get_class_methods($this), function ($method) {
			return substr($method, 0, 4) === 'test';
		}));
	}

	public function runnableMethodCount(): int
	{
		return count($this->runnableMethods());
	}

	function run() {
		$executed = 0;
		foreach ($this->runnableMethods() as $method) {
			echo ">>{$method}>>\n";
			try {
				call_user_func([$this, $method]);
			} catch (Throwable $e) {
				$this->failures++;
				echo "Test {$method} failed with error: {$e->getMessage()}\n";
			} finally {
				echo "<<{$method}<<\n\n";
			}
			$executed++;
		}
		return $executed;
	}

	public function setUp()
	{
	}

	public function tearDown()
	{
	}

	public function assertEquals($a, $b, $message = null): void
	{
		$this->assertTrue($a == $b, $message ? $message : 'Expected '.json_encode($a).' == '.json_encode($b));
	}

	public function assertTrue($bool, $message = null): void
	{
		$message = $message ? $message : 'Expected value to be ' . ($bool ? 'true' : 'false');
		if ($bool) {
			echo "Passed: {$message}\n";
		} else {
			$this->failures++;
			echo "Failed: {$message}\n";
		}
	}
}

// exit() skips auto_append_file. Fail an interrupted TestCase even when its
// output buffer discards the diagnostic, while allowing later cleanup hooks.
// PHP 7.4 disabled functions return NULL; warnings alone can be buffered away.
foreach (array('function_exists', 'getenv', 'basename', 'ini_get', 'register_shutdown_function',
	'defined', 'get_declared_classes', 'is_subclass_of', 'array_values', 'array_filter',
	'get_class_methods', 'substr', 'count', 'call_user_func', 'fwrite', 'strlen', 'is_int', 'getmypid') as $function) {
	if (function_exists($function) !== true) {
		throw new RuntimeException("Required test function unavailable: {$function}");
	}
}
if (getenv('RUTORRENT_PHP_TEST_RUNNER') === '1'
	&& basename((string) ini_get('auto_append_file')) === 'TestCaseRunner.php') {
	// pcntl_fork() children inherit this hook; only the runner's process reports.
	register_shutdown_function(function ($owner) {
		if (defined('RUTORRENT_TESTCASE_RUNNER_FINISHED') || getmypid() !== $owner) return;
		foreach (get_declared_classes() as $cls) {
			if (is_subclass_of($cls, 'TestCase') && (new ReflectionClass($cls))->isInstantiable()) {
				// A later cleanup may exit(0) before our deferred failing exit.
				// STDERR also preserves the refusal when output buffers discard it.
				$message = "\nFailed: TestCase runner did not finish before shutdown\n";
				$written = 0;
				try {
					while ($written < strlen($message)) {
						$remaining = substr($message, $written);
						$count = @fwrite(STDERR, $remaining);
						// Without a complete refusal, a later exit(0) could hide us.
						if (!is_int($count) || $count <= 0 || $count > strlen($remaining)) exit(1);
						$written += $count;
					}
				} catch (Throwable $error) {
					exit(1);
				}
				register_shutdown_function(function () { exit(1); });
				return;
			}
		}
	}, getmypid());
}
