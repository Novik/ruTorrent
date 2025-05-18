#!/bin/bash
set -e

TEST_RUN='
foreach(get_declared_classes() as $cls) {
	if (get_parent_class($cls) == "TestCase") {
		echo "Test: {$cls}\n";
		$obj = new $cls();
		try {
			$obj->setUp();
			$obj->run();
		} catch (Exception $e) {
			echo $e->getMessage()."\n";
			echo $e->getTraceAsString()."\n";
		}
		$obj->tearDown();
	}
}'

for t in $(find php plugins -type f -name '*Test.php')
do
	echo '> php' $t
	DIR=$(dirname "$t")
	php -c php-test.ini -f <(cat <(sed "s@__DIR__@\"$DIR\"@g" "$t") <(echo "$TEST_RUN"))
done
