#!/bin/bash

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

# Exit non-zero if any test file fails, so the suite can gate CI. Two failure
# signals are honoured: a non-zero exit (the self-running TestLib suites end
# with exit($failures)) and failure output (the TestCase runner only prints).
status=0
for t in $(find php plugins -type f -name '*Test.php')
do
	echo '> php' $t
	DIR=$(dirname "$t")
	out=$(php -c php-test.ini -f <(cat <(sed "s@__DIR__@\"$DIR\"@g" "$t") <(echo "$TEST_RUN")) 2>&1)
	code=$?
	printf '%s\n' "$out"
	if [ "$code" -ne 0 ] || printf '%s\n' "$out" | grep -qE '^Failed:|^not ok|failed with error|PHP (Fatal|Parse) error|Uncaught'; then
		status=1
	fi
done

exit $status
