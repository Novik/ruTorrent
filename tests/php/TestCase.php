<?php

class TestCase
{
	function run() {
		foreach (get_class_methods($this) as $method) {
			if (substr($method, 0, 4) == 'test') {
				echo ">>".$method.">>\n";
				call_user_func([$this, $method]);
				echo "\n<<".$method."<<\n";
			}
		}
	}

	public function assertEquals($a, $b): void
	{
		assert($a == $b, 'expected '.json_encode($a).' == '.json_encode($b));
	}

	public function assertTrue($bool, $message=''): void
	{
		assert($bool, $message);
	}
}

