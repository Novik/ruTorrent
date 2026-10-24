#!/bin/bash

script_dir="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)" || exit 1
cd -- "$script_dir" || exit 1

# Exit non-zero if any test file fails, so the suite can gate CI. Two failure
# signals are honoured: a non-zero exit and failure output, including child
# PHP diagnostics. A positive completed result is also required in each file.
status=0
test_manifest="$(mktemp "${TMPDIR:-/tmp}/rutorrent-php-tests.XXXXXX")" || exit 1
trap 'rm -f -- "$test_manifest"' EXIT
if ! find php plugins -type f -name '*Test.php' -print0 > "$test_manifest"; then
	echo 'php-test.sh: cannot list PHP test files' >&2
	exit 1
fi
if [ ! -s "$test_manifest" ]; then
	echo "php-test.sh: no *Test.php files under $PWD/php or $PWD/plugins" >&2
	exit 1
fi
while IFS= read -r -d '' t; do
	printf '> php %s\n' "$t"
	out=$(RUTORRENT_PHP_TEST_RUNNER=1 php -c php-test.ini \
		-d auto_append_file="$script_dir/php/TestCaseRunner.php" -f "$t" 2>&1)
	code=$?
	printf '%s\n' "$out"
	checked=0
	if printf '%s\n' "$out" | grep -q '^Test: '; then
		if printf '%s\n' "$out" | grep -q '^Passed: ' \
			&& printf '%s\n' "$out" | grep -qE '^TestCase runner finished: [1-9][0-9]* classes, [1-9][0-9]* methods$'; then
			checked=1
		fi
	elif printf '%s\n' "$out" | grep -q '^ok - ' \
		&& printf '%s\n' "$out" | grep -qE '^[1-9][0-9]* (tests?, 0 failures|passed, 0 failed)$'; then
		checked=1
	fi
	if [ "$checked" -eq 0 ]; then
		printf 'php-test.sh: no nonempty completed passing result in %s\n' "$t"
	fi
	if [ "$code" -ne 0 ] || [ "$checked" -eq 0 ] \
		|| printf '%s\n' "$out" | grep -qE '^Failed:|^not ok|failed with error|(^|PHP )(Fatal|Parse) error|Uncaught'; then
		status=1
	fi
done < "$test_manifest"

exit $status
