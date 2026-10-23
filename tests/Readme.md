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

An unfinished TestCase reports its refusal through STDERR before later shutdown
cleanup runs. If that diagnostic cannot be written, the process exits immediately.

Run the runner's isolated regression checks with:

```shell
bash tools/php-harness-test.sh
```

