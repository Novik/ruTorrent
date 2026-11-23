<?php

/**
 * php-test.sh fails a test file on any PHP diagnostic (warning, notice,
 * deprecation, fatal error) raised by the file or by a PHP it starts. A test
 * that provokes one on purpose declares it here: the diagnostic must happen,
 * and once seen it no longer counts against the file.
 *
 * A pattern is a PCRE matched against "<Type>: <message>", the type as PHP
 * names it (Warning, Notice, Deprecated, Fatal error, ...) and the message as
 * PHP writes it, which may end in " in <file> on line <n>". PHP 7.4 and 8
 * spell some messages differently ("failed"/"Failed to open stream"), so a
 * pattern usually wants the i flag.
 */
final class ExpectedDiagnostics
{
    private const TYPES = [
        E_WARNING => 'Warning', E_CORE_WARNING => 'Warning', E_COMPILE_WARNING => 'Warning',
        E_USER_WARNING => 'Warning', E_NOTICE => 'Notice', E_USER_NOTICE => 'Notice',
        E_DEPRECATED => 'Deprecated', E_USER_DEPRECATED => 'Deprecated',
        E_RECOVERABLE_ERROR => 'Recoverable fatal error', E_USER_ERROR => 'Fatal error',
        2048 => 'Strict Standards',
    ];

    /**
     * Runs $call and returns what it returns. Each of $patterns must match a
     * diagnostic raised while $call runs, in this process or in a PHP it
     * starts; every diagnostic a pattern matches is taken as expected. A
     * pattern that matches none throws, after $call has run.
     */
    public static function expect(array $patterns, callable $call)
    {
        $seen = array_fill_keys(array_keys($patterns), false);
        $log = (string) getenv('PHP_TEST_ERROR_LOG');
        $offset = self::size($log);
        // In this process the handler takes a matching diagnostic before PHP
        // displays or logs it; anything else goes on as it would have.
        $previous = null;
        $previous = set_error_handler(
            function ($number, $message, $file = '', $line = 0) use ($patterns, &$seen, &$previous) {
                if (error_reporting() & $number) {
                    $text = self::type($number) . ': ' . $message . ' in ' . $file . ' on line ' . $line;
                    $matched = false;
                    foreach ($patterns as $key => $pattern) {
                        if (preg_match($pattern, $text) === 1) {
                            $seen[$key] = true;
                            $matched = true;
                        }
                    }
                    if ($matched) {
                        return true;
                    }
                }
                return is_callable($previous) ? call_user_func($previous, $number, $message, $file, $line) : false;
            },
        );
        try {
            $result = $call();
        } finally {
            restore_error_handler();
        }
        // A PHP the call started logged to the file php-test.sh named. What
        // matches is listed beside it, and php-test.sh leaves those lines out.
        $taken = [];
        if (($log !== '') && (self::size($log) > $offset)) {
            $added = (string) file_get_contents($log, false, null, $offset);
            foreach (explode("\n", $added) as $entry) {
                if (!preg_match('/^\[[^]]*\] PHP ([A-Za-z ]+):  (.*)$/', $entry, $parts)) {
                    continue;
                }
                // One entry per line: the same diagnostic raised twice in
                // one second is two identical lines, and both are taken.
                $matched = false;
                foreach ($patterns as $key => $pattern) {
                    if (preg_match($pattern, $parts[1] . ': ' . $parts[2]) === 1) {
                        $seen[$key] = true;
                        $matched = true;
                    }
                }
                if ($matched) {
                    $taken[] = $entry;
                }
            }
        }
        if ($taken !== []) {
            $list = (string) getenv('PHP_TEST_EXPECTED_DIAGNOSTICS');
            if (($list === '')
                || (file_put_contents($list, implode("\n", $taken) . "\n", FILE_APPEND) === false)) {
                throw new RuntimeException('expected diagnostics could not be recorded in PHP_TEST_EXPECTED_DIAGNOSTICS');
            }
        }
        $missing = array_keys(array_filter($seen, function ($hit) {
            return !$hit;
        }));
        if ($missing !== []) {
            $wanted = array_map(function ($key) use ($patterns) {
                return $patterns[$key];
            }, $missing);
            throw new RuntimeException('an expected PHP diagnostic did not happen: ' . implode(', ', $wanted)
                . (($log === '') ? ' (a PHP the test starts is only seen through php-test.sh)' : ''));
        }
        return $result;
    }

    private static function type($number)
    {
        return self::TYPES[$number] ?? 'Unknown error';
    }

    private static function size($file)
    {
        if ($file === '') {
            return 0;
        }
        clearstatcache(true, $file);
        $size = is_file($file) ? filesize($file) : 0;
        return ($size === false) ? 0 : $size;
    }
}
