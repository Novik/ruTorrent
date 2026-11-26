#!/bin/bash

script_dir="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)" || exit 1
cd -- "$script_dir" || exit 1

# A umask that masks the owner's own bits, such as 0177, leaves every directory
# a fixture creates with mkdir() unusable to the test that created it. Clear
# only those bits; the group and other bits of the caller's umask are kept.
umask u=rwx

# Every PHP the suite runs reads php-test.d/ (php-test.ini) after the system's
# scan directory: the test itself, and every PHP a test starts through
# shell_exec, exec, proc_open or php -S, with or without -c. The system
# configuration is still read; the suite's settings are read last and win. An
# empty entry in PHP_INI_SCAN_DIR stands for PHP's built-in scan directory.
if [ -n "${PHP_INI_SCAN_DIR+set}" ] && [ -z "$PHP_INI_SCAN_DIR" ]; then
	export PHP_INI_SCAN_DIR="$script_dir/php-test.d"
else
	export PHP_INI_SCAN_DIR="${PHP_INI_SCAN_DIR-}:$script_dir/php-test.d"
fi

# PHP's own diagnostics in log $1 that are not listed in $2, the lines a test
# declared through ExpectedDiagnostics and saw. Only PHP's prefixes count: a
# line the code under test writes with error_log() is not a diagnostic.
unexpected_diagnostics() {
	awk 'FILENAME == ARGV[1] { expected[$0]++; next }
		/^\[[^]]*\] PHP (Fatal error|Recoverable fatal error|Parse error|Warning|Notice|Deprecated|Strict Standards|Unknown error): / {
			if (expected[$0] > 0) { expected[$0]--; next }
			print
		}' "$2" "$1"
}

# Exit non-zero if any test file fails, so the suite can gate CI. A file fails
# on a non-zero exit, on failure output, without a positive completed result,
# and on any PHP diagnostic from the file or from a PHP it starts: every PHP
# logs its diagnostics to the file's own log (php-test.ini), and a warning,
# notice or deprecation printed in the file's output counts too.
status=0
failed_files=()
test_manifest="$(mktemp "${TMPDIR:-/tmp}/rutorrent-php-tests.XXXXXX")" || exit 1
log_dir="$(mktemp -d "${TMPDIR:-/tmp}/rutorrent-php-test-logs.XXXXXX")" || exit 1
trap 'rm -rf -- "$test_manifest" "$log_dir"' EXIT
if ! find php plugins -type f -name '*Test.php' -print0 > "$test_manifest"; then
	echo 'php-test.sh: cannot list PHP test files' >&2
	exit 1
fi
if [ ! -s "$test_manifest" ]; then
	echo "php-test.sh: no *Test.php files under $PWD/php or $PWD/plugins" >&2
	exit 1
fi
files=0
while IFS= read -r -d '' t; do
	printf '> php %s\n' "$t"
	files=$((files + 1))
	export PHP_TEST_ERROR_LOG="$log_dir/$files.log"
	export PHP_TEST_EXPECTED_DIAGNOSTICS="$log_dir/$files.expected"
	: > "$PHP_TEST_ERROR_LOG"
	: > "$PHP_TEST_EXPECTED_DIAGNOSTICS"
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
	reasons=()
	if [ "$code" -ne 0 ]; then
		reasons+=("exit code $code")
	fi
	if [ "$checked" -eq 0 ]; then
		reasons+=('no completed passing result')
	fi
	if printf '%s\n' "$out" | grep -qE '^Failed:|^not ok'; then
		reasons+=('failed assertions')
	fi
	if printf '%s\n' "$out" | grep -qE 'failed with error|(^|PHP )(Fatal|Parse) error|Uncaught'; then
		reasons+=('error output')
	fi
	unexpected=$(unexpected_diagnostics "$PHP_TEST_ERROR_LOG" "$PHP_TEST_EXPECTED_DIAGNOSTICS")
	if [ -n "$unexpected" ] \
		|| printf '%s\n' "$out" | grep -qE '^(PHP )?(Warning|Notice|Deprecated|Strict Standards): '; then
		printf 'php-test.sh: PHP diagnostics in %s:\n' "$t"
		if [ -n "$unexpected" ]; then
			printf '%s\n' "$unexpected"
		else
			printf '%s\n' "$out" | grep -E '^(PHP )?(Warning|Notice|Deprecated|Strict Standards): '
		fi
		reasons+=('PHP diagnostics')
	fi
	# Every failing file is named with its reasons, so a log always says which
	# file failed and why. Without a failure line of its own (an exit code or an
	# unfinished run), the last lines show where the file stopped.
	if [ "${#reasons[@]}" -gt 0 ]; then
		status=1
		reason=$(printf '%s, ' "${reasons[@]}")
		reason=${reason%, }
		printf 'php-test.sh: FAILED %s: %s\n' "$t" "$reason"
		failed_files+=("$t")
		if ! printf '%s\n' "$out" | grep -qE '^Failed:|^not ok'; then
			if [ -n "$out" ]; then
				printf 'php-test.sh: last lines of %s:\n' "$t"
				printf '%s\n' "$out" | tail -n 10
			else
				printf 'php-test.sh: last lines of %s: (no output)\n' "$t"
			fi
		fi
	fi
done < "$test_manifest"

if [ "${#failed_files[@]}" -gt 0 ]; then
	printf 'php-test.sh: %s of %s test files failed: %s\n' "${#failed_files[@]}" "$files" "${failed_files[*]}"
fi
exit $status
