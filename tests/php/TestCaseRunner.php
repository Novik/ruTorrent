<?php

// auto_append_file executes declarations in their original source file, then
// runs their TestCase classes without rewriting __FILE__ or __DIR__.
$failures = 0;
$classes = 0;
$methods = 0;
foreach (get_declared_classes() as $cls) {
	if (!is_subclass_of($cls, 'TestCase') || !(new ReflectionClass($cls))->isInstantiable()) continue;
	$classes++;
	echo "\nTest: {$cls}\n";
	$obj = null;
	try {
		$obj = new $cls();
		$expected = $obj->runnableMethodCount();
		if ($expected === 0) throw new RuntimeException('has no test methods');
		$obj->setUp();
		$executed = $obj->run();
		if ($executed !== $expected) throw new RuntimeException("ran {$executed} of {$expected} test methods");
		$methods += $executed;
	} catch (Throwable $error) {
		$failures++;
		echo "Test {$cls} failed with error: {$error->getMessage()}\n";
	} finally {
		if ($obj !== null) {
			// Setup can acquire resources before throwing; cleanup still owns them.
			try {
				$obj->tearDown();
			} catch (Throwable $error) {
				$failures++;
				echo "Test {$cls} tearDown failed with error: {$error->getMessage()}\n";
			}
			$failures += $obj->failureCount();
		}
	}
}
define('RUTORRENT_TESTCASE_RUNNER_FINISHED', true);
echo "TestCase runner finished: {$classes} classes, {$methods} methods\n";
if ($failures > 0) exit(1);
