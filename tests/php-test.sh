#!/bin/bash
set -e

TEST_RUN='
echo "Running tests...\n";
foreach(get_declared_classes() as $cls) {
	if (get_parent_class($cls) == "TestCase") {
		echo "Test: ".$cls."\n";
		$obj = new $cls;
		$obj->run();
	}
}'

for t in $(find php plugins -type f -name '*Test.php')
do
	echo '> php' $t
	DIR=$(dirname "$t")
	php -c php-test.ini -f <(cat <(sed "s@__DIR__@\"$DIR\"@g" "$t") <(echo "$TEST_RUN"))
done
