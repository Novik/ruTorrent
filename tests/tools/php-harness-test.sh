#!/usr/bin/env bash
set -euo pipefail

root="$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)"
scratch="$(mktemp -d "${TMPDIR:-/tmp}/rt-php-harness.XXXXXX")"
trap 'rm -rf -- "$scratch"' EXIT
mkdir -p "$scratch/tests/php" "$scratch/tests/plugins" "$scratch/bin"
cp "$root/tests/php-test.sh" "$root/tests/php-test.ini" "$scratch/tests/"
cp "$root/tests/php/TestCase.php" "$scratch/tests/php/"
if [[ -f "$root/tests/php/TestCaseRunner.php" ]]; then
	cp "$root/tests/php/TestCaseRunner.php" "$scratch/tests/php/"
fi
cp "$root/tests/plugins/rutracker_check/TestLib.php" "$scratch/tests/plugins/"
failures=0
checks=0
test_file="$scratch/tests/plugins/FixtureTest.php"

check_run() {
	local name="$1" expected_status="$2"
	shift 2
	local code=0
	(cd "$scratch" && CDPATH=. bash tests/php-test.sh) > "$scratch/output" 2>&1 || code=$?
	checks=$((checks + 1))
	if { [[ "$expected_status" == pass && "$code" -ne 0 ]] \
		|| [[ "$expected_status" == fail && "$code" -eq 0 ]]; }; then
		printf 'not ok - %s (exit %s)\n' "$name" "$code" >&2
		cat "$scratch/output" >&2
		failures=$((failures + 1))
		return
	fi
	for expected in "$@"; do
		if ! grep -Fq -- "$expected" "$scratch/output"; then
			printf 'not ok - %s (missing %s)\n' "$name" "$expected" >&2
			cat "$scratch/output" >&2
			failures=$((failures + 1))
			return
		fi
	done
	printf 'ok - %s\n' "$name"
}

# Both invocation and source identity must survive path separators used by
# the old shell word splitting and textual __DIR__ replacement.
mkdir -p "$scratch/tests/plugins/a b@c"
cat > "$scratch/tests/plugins/a b@c/Identity Test.php" <<'PHP'
<?php
require_once(__DIR__ . '/../../php/TestCase.php');
class IdentityTest extends TestCase {
    public function testSource() {
        $this->assertTrue(is_file(__FILE__), 'source file can be reopened');
        $this->assertEquals(dirname(__FILE__), __DIR__, 'source directory is real');
        $this->assertEquals('__' . 'DIR__', '__DIR__', 'literal magic constant stays literal');
    }
}
PHP
check_run 'real source path with spaces and @' pass 'Passed: source file can be reopened' 'Passed: literal magic constant stays literal'
rm "$scratch/tests/plugins/a b@c/Identity Test.php"

check_run 'empty discovery fails' fail 'no'
printf '<?php\n' > "$test_file"
check_run 'file without tests fails' fail
cat > "$test_file" <<'PHP'
<?php
require_once(__DIR__ . '/../php/TestCase.php');
class EmptyTest extends TestCase {}
PHP
check_run 'class without methods fails' fail
cat > "$test_file" <<'PHP'
<?php
require_once(__DIR__ . '/../php/TestCase.php');
class EmptyMethodTest extends TestCase { public function testEmpty() {} }
PHP
check_run 'method without assertions or result fails' fail

cat > "$test_file" <<'PHP'
<?php
require_once(__DIR__ . '/../php/TestCase.php');
abstract class SharedTestBase extends TestCase {}
class InheritedTest extends SharedTestBase {
    public function testInherited() { $this->assertTrue(true, 'indirect subclass ran'); }
}
PHP
check_run 'indirect concrete subclasses execute' pass 'Passed: indirect subclass ran'

cat > "$test_file" <<'PHP'
<?php
require_once(__DIR__ . '/../php/TestCase.php');
class MidlineFailureTest extends TestCase {
    public function testAssertion() {
        echo 'no newline';
        $this->assertTrue(false, 'false assertion');
        $this->assertTrue(true, 'passing assertion cannot hide failure');
    }
}
PHP
check_run 'midline false assertion fails by status' fail 'Failed: false assertion'

cat > "$test_file" <<'PHP'
<?php
echo "ok - deliberate nonzero status\n1 tests, 0 failures\n";
exit(7);
PHP
check_run 'nonzero status overrides a passing summary' fail 'ok - deliberate nonzero status'

for error in Exception Error; do
	for phase in constructor setUp test tearDown; do
		cat > "$test_file" <<'PHP'
<?php
require_once(__DIR__ . '/../php/TestCase.php');
class ThrowingTest extends TestCase {
    private function maybeThrow($phase) {
        if (getenv('HARNESS_PHASE') === $phase) {
            $error = getenv('HARNESS_ERROR');
            throw new $error('deliberate ' . $phase . ' failure');
        }
    }
    public function __construct() { $this->maybeThrow('constructor'); }
    public function setUp() { $this->maybeThrow('setUp'); }
    public function testFirst() { $this->maybeThrow('test'); }
    public function testSecond() { $this->assertTrue(true, 'later method ran'); }
    public function tearDown() {
        file_put_contents(__DIR__ . '/cleanup', 'done');
        $this->maybeThrow('tearDown');
    }
}
class LaterTest extends TestCase {
    public function testLaterClass() { $this->assertTrue(true, 'later class ran'); }
}
PHP
		export HARNESS_PHASE="$phase" HARNESS_ERROR="$error"
		check_run "$error during $phase is counted and later class runs" fail "deliberate $phase failure" 'Passed: later class ran'
		if [[ "$phase" != constructor && ! -f "$scratch/tests/plugins/cleanup" ]]; then
			printf 'not ok - cleanup missing after %s in %s\n' "$error" "$phase" >&2
			failures=$((failures + 1))
		fi
		if [[ "$phase" == test ]] && ! grep -Fq 'Passed: later method ran' "$scratch/output"; then
			echo 'not ok - a throwing method skipped later methods' >&2
			failures=$((failures + 1))
		fi
		rm -f "$scratch/tests/plugins/cleanup"
	done
done
unset HARNESS_PHASE HARNESS_ERROR

# Keep the established once-per-class lifecycle: real suites share fixture
# state across methods and may override setup/cleanup without class hooks.
cat > "$test_file" <<'PHP'
<?php
require_once(__DIR__ . '/../php/TestCase.php');
class LifecycleTest extends TestCase {
    private $counter = 0;
    public function setUp() { $this->counter++; }
    public function testFirst() { $this->assertEquals(1, $this->counter++, 'first method shares setup'); }
    public function testSecond() { $this->assertEquals(2, $this->counter++, 'second method shares setup'); }
    public function tearDown() { $this->assertEquals(3, $this->counter, 'one class cleanup'); }
}
PHP
check_run 'once-per-class lifecycle is preserved' pass 'Passed: one class cleanup'

cat > "$test_file" <<'PHP'
<?php
require_once(__DIR__ . '/../php/TestCase.php');
class SkippingTest extends TestCase {
    public function testFirst() { $this->assertTrue(true, 'first method ran'); }
    public function testSecond() { $this->assertTrue(false, 'second method must run'); }
    public function run() { $this->testFirst(); return 1; }
}
PHP
check_run 'a skipped declared method fails' fail 'ran 1 of 2 test methods'

cat > "$test_file" <<'PHP'
<?php
require_once(__DIR__ . '/../php/TestCase.php');
class PassingTest extends TestCase { public function testPass() { $this->assertTrue(true); } }
class EmptySecondTest extends TestCase {}
PHP
check_run 'one passing class cannot hide an empty class' fail 'has no test methods'

for buffer in 0 1; do
	cat > "$test_file" <<'PHP'
<?php
require_once(__DIR__ . '/../php/TestCase.php');
class ExitTest extends TestCase {
    public function testUnreachable() { $this->assertTrue(false, 'must execute'); }
}
register_shutdown_function(function () { file_put_contents(__DIR__ . '/cleanup', 'done'); });
echo "ok - child\n1 tests, 0 failures\n";
if (getenv('HARNESS_BUFFER') === '1') ob_start(function ($bytes) { return ''; });
exit(0);
PHP
	export HARNESS_BUFFER="$buffer"
	check_run "exit before TestCase execution fails (buffer $buffer)" fail
	if [[ ! -f "$scratch/tests/plugins/cleanup" ]]; then
		echo 'not ok - incomplete runner prevented shutdown cleanup' >&2
		failures=$((failures + 1))
	fi
	rm -f "$scratch/tests/plugins/cleanup"
done
unset HARNESS_BUFFER

# A later shutdown callback may exit zero before the guard's deferred exit.
# STDERR bypasses output buffering, but its short/failed writes need coverage.
cat > "$test_file" <<'PHP'
<?php
require_once(__DIR__ . '/../php/TestCase.php');
echo "ok - child\n1 tests, 0 failures\n";
ob_start(function ($bytes) { return ''; });
class ShutdownExitTest extends TestCase {
    public function testUnreachable() { $this->assertTrue(false, 'must execute'); }
}
class DiagnosticFilter extends php_user_filter {
    public function filter($in, $out, &$consumed, $closing) {
        file_put_contents(__DIR__ . '/filter-entered', 'yes');
        if (getenv('HARNESS_STDERR_MODE') === 'failed') return PSFS_ERR_FATAL;
        if (getenv('HARNESS_STDERR_MODE') === 'zero') return PSFS_PASS_ON;
        while ($bucket = stream_bucket_make_writeable($in)) {
            $bucket->data = substr($bucket->data, 0, 3);
            $consumed += strlen($bucket->data);
            stream_bucket_append($out, $bucket);
        }
        return PSFS_PASS_ON;
    }
}
$mode = getenv('HARNESS_STDERR_MODE');
if ($mode === 'closed') fclose(STDERR);
elseif ($mode !== 'normal') {
    stream_filter_register('harness-diagnostic', 'DiagnosticFilter');
    stream_filter_append(STDERR, 'harness-diagnostic', STREAM_FILTER_WRITE);
}
register_shutdown_function(function () {
    file_put_contents(__DIR__ . '/cleanup', 'done');
    exit(0);
});
exit(0);
PHP
for mode in normal partial failed zero closed; do
	export HARNESS_STDERR_MODE="$mode"
	if [[ "$mode" == normal ]]; then
		check_run "cleanup exit zero cannot hide unfinished runner ($mode STDERR)" fail 'Failed: TestCase runner did not finish before shutdown'
	else
		check_run "unfinished diagnostic fails closed ($mode STDERR)" fail
	fi
	# PHP7.4 can refuse a user filter during shutdown before calling filter().
	# That is a failed write; partial-write completion is checked when admitted.
	if [[ "$mode" == normal || ( "$mode" == partial && -f "$scratch/tests/plugins/filter-entered" ) ]]; then
		if ! grep -Fq 'Failed: TestCase runner did not finish before shutdown' "$scratch/output" \
			|| [[ ! -f "$scratch/tests/plugins/cleanup" ]]; then
			printf 'not ok - %s diagnostic skipped shutdown cleanup\n' "$mode" >&2
			failures=$((failures + 1))
		fi
	fi
	rm -f "$scratch/tests/plugins/cleanup" "$scratch/tests/plugins/filter-entered"
done
unset HARNESS_STDERR_MODE

# Counted Throwables must fail even when an output buffer hides both their
# diagnostic and the runner's class/method output after a child summary.
cat > "$test_file" <<'PHP'
<?php
require_once(__DIR__ . '/../php/TestCase.php');
echo "ok - child\n1 tests, 0 failures\n";
ob_start(function ($bytes) { return ''; });
class BufferedThrowableTest extends TestCase {
    private function maybeThrow($phase) {
        if (getenv('HARNESS_PHASE') === $phase) throw new Error('buffered ' . $phase);
    }
    public function __construct() { $this->maybeThrow('constructor'); }
    public function setUp() { $this->maybeThrow('setUp'); }
    public function testThrows() { $this->maybeThrow('test'); }
    public function tearDown() { $this->maybeThrow('tearDown'); }
}
PHP
for phase in constructor setUp test tearDown; do
	export HARNESS_PHASE="$phase"
	check_run "buffered $phase Throwable fails by status" fail
done
unset HARNESS_PHASE

cat > "$test_file" <<'PHP'
<?php
require_once(__DIR__ . '/../php/TestCase.php');
class UnfinishedTest extends TestCase {
    public function testUnreachable() { $this->assertTrue(false, 'must execute'); }
}
echo "ok - child\n1 tests, 0 failures\n";
exit(0);
PHP
real_php="$(command -v php)"
for dependency in getenv ini_get basename register_shutdown_function defined get_declared_classes is_subclass_of getmypid; do
	printf '#!/usr/bin/env bash\nexec %q -d disable_functions=%q "$@"\n' "$real_php" "$dependency" > "$scratch/bin/php"
	chmod +x "$scratch/bin/php"
	PATH="$scratch/bin:$PATH" check_run "disabled $dependency cannot hide an unfinished run" fail "Required test function unavailable: $dependency"
done
rm "$scratch/bin/php"

# Diagnostic output is not an independent status channel: a buffer can discard
# disabled-function warnings before the TestCase guard ever registers itself.
cat > "$test_file" <<'PHP'
<?php
echo "ok - child\n1 tests, 0 failures\n";
ob_start(function ($bytes) { return ''; });
require_once(__DIR__ . '/../php/TestCase.php');
class BufferedUnfinishedTest extends TestCase {
    public function testUnreachable() { $this->assertTrue(false, 'must execute'); }
}
exit(0);
PHP
for dependency in function_exists getenv ini_get basename register_shutdown_function get_declared_classes is_subclass_of fwrite strlen is_int getmypid; do
	printf '#!/usr/bin/env bash\nexec %q -d disable_functions=%q "$@"\n' "$real_php" "$dependency" > "$scratch/bin/php"
	chmod +x "$scratch/bin/php"
	PATH="$scratch/bin:$PATH" check_run "buffered disabled $dependency still fails by status" fail
done
rm "$scratch/bin/php"

cat > "$test_file" <<'PHP'
<?php
require_once(__DIR__ . '/../php/TestCase.php');
class PassingTest extends TestCase {
    public function testPass() { $this->assertTrue(true, 'method executed'); }
}
PHP
for dependency in array_values array_filter get_class_methods substr count call_user_func; do
	printf '#!/usr/bin/env bash\nexec %q -d disable_functions=%q "$@"\n' "$real_php" "$dependency" > "$scratch/bin/php"
	chmod +x "$scratch/bin/php"
	PATH="$scratch/bin:$PATH" check_run "disabled $dependency cannot fake method execution" fail
done
rm "$scratch/bin/php"

cat > "$test_file" <<'PHP'
<?php
require_once(__DIR__ . '/../php/TestCase.php');
class ExitInMethodTest extends TestCase {
    public function testExit() {
        $this->assertTrue(true, 'before early exit');
        echo "ok - child\n1 tests, 0 failures\n";
        exit(0);
    }
    public function testUnreachable() { $this->assertTrue(false); }
}
PHP
check_run 'exit inside a method fails despite child summary' fail

# A pcntl_fork() child inherits the shutdown hook; its exit() must not fail the run.
if php -c "$scratch/tests/php-test.ini" -r 'exit(function_exists("pcntl_fork") ? 0 : 1);'; then
	cat > "$test_file" <<'PHP'
<?php
require_once(__DIR__ . '/../php/TestCase.php');
class ForkedChildExitTest extends TestCase {
    public function testChildExit() {
        $pid = pcntl_fork();
        if ($pid === 0) exit(0);
        $this->assertTrue($pid > 0 && pcntl_waitpid($pid, $status) === $pid && pcntl_wexitstatus($status) === 0,
            'forked child exits with its own status');
    }
}
PHP
	check_run 'forked child exit keeps a passing run' pass 'Passed: forked child exits with its own status'
else
	printf 'skip - forked child exit keeps a passing run (no pcntl)\n'
fi

for cases in 0 1; do
	cat > "$test_file" <<'PHP'
<?php
require_once(__DIR__ . '/TestLib.php');
$suite = new StrictTestSuite();
if (getenv('HARNESS_CASES') === '1') $suite->test('standalone passes', function () { strictAssertTrue(true, 'passes'); });
exit($suite->run());
PHP
	export HARNESS_CASES="$cases"
	if [[ "$cases" == 0 ]]; then
		check_run 'zero standalone cases fail' fail '0 tests, 0 failures'
	else
		check_run 'existing standalone TestLib remains supported' pass 'ok - standalone passes' '1 tests, 0 failures'
	fi
done
unset HARNESS_CASES
cat > "$test_file" <<'PHP'
<?php
require_once(__DIR__ . '/TestLib.php');
$suite = new StrictTestSuite();
$suite->test('standalone fails', function () { strictAssertTrue(false, 'deliberate assertion'); });
exit($suite->run());
PHP
check_run 'standalone failure remains red' fail 'not ok - standalone fails'

# The shipped Snoopy header suites report passed/failed counts rather than
# TestLib's tests/failures. Both contracts still need a real case and count.
cat > "$test_file" <<'PHP'
<?php
if (getenv('HARNESS_LEGACY_MARKER') === '1') echo "ok - existing standalone case ran\n";
echo getenv('HARNESS_LEGACY_SUMMARY') . "\n";
PHP
export HARNESS_LEGACY_MARKER=1
for passed in 4 9; do
	export HARNESS_LEGACY_SUMMARY="$passed passed, 0 failed"
	check_run "existing $passed passed summary remains supported" pass 'ok - existing standalone case ran' "$HARNESS_LEGACY_SUMMARY"
done
export HARNESS_LEGACY_SUMMARY='0 passed, 0 failed'
check_run 'zero passed summary fails even with a case marker' fail "$HARNESS_LEGACY_SUMMARY"
export HARNESS_LEGACY_SUMMARY='3 passed, 1 failed'
check_run 'nonzero failed summary fails despite exit zero' fail "$HARNESS_LEGACY_SUMMARY"
export HARNESS_LEGACY_SUMMARY='4 passed, 0 failed' HARNESS_LEGACY_MARKER=0
check_run 'passed summary without an executed case fails' fail "$HARNESS_LEGACY_SUMMARY"
unset HARNESS_LEGACY_MARKER HARNESS_LEGACY_SUMMARY

for diagnostic in Fatal Parse; do
	cat > "$test_file" <<'PHP'
<?php
$code = getenv('HARNESS_DIAGNOSTIC') === 'Parse' ? 'invalid PHP ;' : 'trigger_error("child failed", E_USER_ERROR);';
echo shell_exec(escapeshellarg(PHP_BINARY) . ' -c ' . escapeshellarg(__DIR__ . '/../php-test.ini')
    . ' -r ' . escapeshellarg($code) . ' 2>&1');
echo "ok - parent passed\n1 tests, 0 failures\n";
PHP
	export HARNESS_DIAGNOSTIC="$diagnostic"
	check_run "child $diagnostic diagnostic fails a passing parent" fail "$diagnostic error:" 'ok - parent passed'
done
unset HARNESS_DIAGNOSTIC
rm "$test_file"

# Pin both aggregate status and continuing to the next file after a failure.
cat > "$test_file" <<'PHP'
<?php
require_once(__DIR__ . '/TestLib.php');
$suite = new StrictTestSuite();
$suite->test('first file fails', function () { strictAssertTrue(false, 'expected failure'); });
exit($suite->run());
PHP
printf '<?php echo "ok - later file ran\n1 tests, 0 failures\n";\n' > "$scratch/tests/plugins/LaterTest.php"
cat > "$scratch/bin/find" <<'SH'
#!/usr/bin/env bash
printf 'plugins/FixtureTest.php\0plugins/LaterTest.php\0'
SH
chmod +x "$scratch/bin/find"
PATH="$scratch/bin:$PATH" check_run 'failed status survives a later passing file' fail 'not ok - first file fails' 'ok - later file ran'
rm "$scratch/bin/find" "$scratch/tests/plugins/LaterTest.php" "$test_file"

# A failed enumeration must not accept the partial list written before failure.
cat > "$scratch/bin/find" <<'SH'
#!/usr/bin/env bash
printf 'plugins/PartialTest.php\0'
exit 1
SH
chmod +x "$scratch/bin/find"
printf '<?php echo "ok - partial\n1 tests, 0 failures\n";\n' > "$scratch/tests/plugins/PartialTest.php"
PATH="$scratch/bin:$PATH" check_run 'failed discovery with partial results fails' fail
rm "$scratch/bin/find" "$scratch/tests/plugins/PartialTest.php"

printf 'php-harness-test.sh: %s checks, %s failures\n' "$checks" "$failures"
[[ "$failures" -eq 0 ]]
