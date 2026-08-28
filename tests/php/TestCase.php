<?php

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
		$this->assertTrue($a == $b, $message ? $message : 'Expected '.json_encode($a).' == '.json_encode($b));
	}

	public function assertTrue($bool, $message = null): void
	{
		$message = $message ? $message : 'Expected value to be ' . ($bool ? 'true' : 'false');
		if ($bool) {
			echo "Passed: {$message}\n";
		} else {
			echo "Failed: {$message}\n";
		}
	}
}
