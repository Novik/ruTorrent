# Tests

## Test javascript
```shell
npm install
npm test
```

## Test php
```shell
./php-test.sh
```

The PHP runner executes each `*Test.php` in its original source file. `TestCase`
classes keep one `setUp()` and `tearDown()` per class; thrown `Throwable`s and
failed assertions fail the file, while later methods and classes still run.
An interrupted or empty TestCase run fails even if a child prints a passing
summary. Standalone suites must print at least one `ok - ` case and a positive
`N tests, 0 failures` or `N passed, 0 failed` summary and exit successfully. Child fatal/parse diagnostics
also fail the containing file.

Any PHP diagnostic fails the file as well: a warning, notice, deprecation or
fatal error raised by the test or by a PHP it starts (`shell_exec`, `exec`,
`proc_open`, `php -S`, a forked stand-in), even when the test keeps that
output to itself. The runner appends `php-test.d/` to `PHP_INI_SCAN_DIR`, so
every PHP reads `php-test.ini` after the system configuration, and that file
logs each diagnostic to a log of the test file's own. Lines written with
`error_log()` by the code under test do not count. A test that provokes a
diagnostic on purpose declares it with `ExpectedDiagnostics::expect()`
(`php/ExpectedDiagnostics.php`): the diagnostic must then happen, and it no
longer counts. `php/SuiteSettingsTest.php` checks that the settings hold in
the test process and in the PHPs it starts.

The runner clears the owner's bits of the umask it is started with
(`umask u=rwx`), so fixture directories made with a plain `mkdir()` stay usable
under a umask such as 0177. The group and other bits are kept.

An unfinished TestCase reports its refusal through STDERR before later shutdown
cleanup runs. If that diagnostic cannot be written, the process exits immediately.

Run the runner's isolated regression checks with:

```shell
bash tools/php-harness-test.sh
```

