#!/bin/bash

# The test list and php-test.ini are relative to this directory, so move here
# whatever the caller's cwd. CDPATH would redirect a relative cd and print the
# new directory, which the DIR= line below would capture into its sed pattern.
unset CDPATH
cd -- "$(dirname -- "$0")" || exit 1

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
test_files=$(find php plugins -type f -name '*Test.php')
if [ -z "$test_files" ]; then
	echo "php-test.sh: no *Test.php files under $PWD/php or $PWD/plugins" >&2
	exit 1
fi
for t in $test_files
do
	echo '> php' $t
	# Absolute: this stands in for __DIR__ below, and a relative path makes a
	# fixture symlink resolve against the wrong directory.
	DIR=$(cd "$(dirname "$t")" && pwd)
	out=$(php -c php-test.ini -f <(cat <(sed "s@__DIR__@\"$DIR\"@g" "$t") <(echo "$TEST_RUN")) 2>&1)
	code=$?
	printf '%s\n' "$out"
	if [ "$code" -ne 0 ] || printf '%s\n' "$out" | grep -qE '^Failed:|^not ok|failed with error|PHP (Fatal|Parse) error|Uncaught'; then
		status=1
	fi
done

exit $status
