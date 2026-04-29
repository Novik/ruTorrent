<?php

foreach (['assert.exception' => 0, 'assert.bail'=> 0] as $configuration => $value) {
	if ($value !== ($current = ini_get($configuration))) {
		@ini_set($configuration, $value);
	}
}

class TestCase
{
	function run() {
		foreach (get_class_methods($this) as $method) {
			if (substr($method, 0, 4) == 'test') {
				echo ">>{$method}>>\n";
				try {
					call_user_func([$this, $method]);
				} catch (Exception $e) {
					echo "Test {$method} failed with error: {$e->getMessage()}\n";
				}
				echo "<<{$method}<<\n\n";
			}
		}
	}

	public function setUp()
	{
	}

	public function tearDown()
	{
	}

	public function assertEquals($a, $b, $message = null): void
	{
		$message = $message ? $message : 'Expected '.json_encode($a).' == '.json_encode($b);
		if (@assert($a == $b, $message)) {
			echo "Passed: {$message}\n";
		} else {
			echo "Failed: {$message}\n";
		}
	}

	public function assertTrue($bool, $message = null): void
	{
		$message = $message ? $message : 'Expected value to be ' . ($bool ? 'true' : 'false');
		if (@assert($bool, $message)) {
			echo "Passed: {$message}\n";
		} else {
			echo "Failed: {$message}\n";
		}
	}
}
