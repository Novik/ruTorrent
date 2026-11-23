<?php

require_once(__DIR__ . '/TestCase.php');
require_once(__DIR__ . '/ExpectedDiagnostics.php');

// php-test.ini is the configuration the suite runs under, and php-test.sh has
// every PHP read it last through PHP_INI_SCAN_DIR: the test process and every
// PHP a test starts, which is what lets a diagnostic raised in a child fail
// the test file. Each setting in php-test.ini must hold in this process and
// in a PHP started through shell_exec() and through proc_open().
class SuiteSettingsTest extends TestCase
{
    public function testThisProcessRunsWithTheSuiteSettings()
    {
        $this->assertSettings($this->current(), 'the test process');
    }

    public function testAPhpStartedThroughAShellRunsWithTheSuiteSettings()
    {
        $out = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($this->probe()) . ' 2>&1');
        $this->assertSettings(json_decode($out, true), 'a PHP started through shell_exec(): ' . $out);
    }

    public function testAPhpStartedThroughProcOpenRunsWithTheSuiteSettings()
    {
        $process = proc_open([PHP_BINARY, '-r', $this->probe()], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->assertTrue(is_resource($process), 'proc_open() starts a PHP');
        $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        $this->assertSettings(json_decode($out, true), 'a PHP started through proc_open(): ' . $out);
    }

    // The log is what makes a child's diagnostic count; check that one really
    // arrives, from a PHP that was started with its own -c, and that the
    // expectation takes it.
    public function testADiagnosticOfAChildReachesTheFilesLog()
    {
        $this->assertTrue(getenv('PHP_TEST_ERROR_LOG') !== false, 'php-test.sh names the log (PHP_TEST_ERROR_LOG)');
        $code = 'trigger_error("suite settings probe", E_USER_NOTICE);';
        ExpectedDiagnostics::expect(['/^Notice: suite settings probe in /'], function () use ($code) {
            exec(escapeshellarg(PHP_BINARY) . ' -c ' . escapeshellarg(__DIR__ . '/../php-test.ini')
                . ' -d display_errors=0 -r ' . escapeshellarg($code), $output, $exit);
            $this->assertEquals(0, $exit, 'the child ran');
        });
        $this->assertTrue(true, 'the notice of a child was logged and taken as expected');
    }

    public function testAnExpectedDiagnosticThatDoesNotHappenFails()
    {
        $thrown = null;
        try {
            ExpectedDiagnostics::expect(['/^Warning: never raised/'], function () {
                return 1;
            });
        } catch (RuntimeException $error) {
            $thrown = $error->getMessage();
        }
        $this->assertTrue(
            is_string($thrown) && (strpos($thrown, 'did not happen') !== false),
            'an expectation that is not met throws: ' . json_encode($thrown),
        );
    }

    private function probe()
    {
        return 'echo json_encode(["settings" => array_map("ini_get", ' . var_export(array_keys($this->expected()), true)
            . '), "scanned" => (string) php_ini_scanned_files()]);';
    }

    private function current()
    {
        return ['settings' => array_map('ini_get', array_keys($this->expected())),
            'scanned' => (string) php_ini_scanned_files()];
    }

    private function assertSettings($got, $where)
    {
        $this->assertTrue(is_array($got) && isset($got['settings'], $got['scanned']), 'settings read in ' . $where);
        if (!is_array($got) || !isset($got['settings'], $got['scanned'])) {
            return;
        }
        $keys = array_keys($this->expected());
        foreach ($keys as $i => $key) {
            $this->assertEquals($this->expected()[$key], $got['settings'][$i] ?? null, "{$key} in {$where}");
        }
        $scanned = array_values(array_filter(array_map('trim', explode(',', $got['scanned']))));
        $last = end($scanned);
        $this->assertEquals(
            realpath(__DIR__ . '/../php-test.d/php-test.ini'),
            is_string($last) ? realpath($last) : false,
            'php-test.d/php-test.ini is the last ini file read in ' . $where,
        );
    }

    // A directive of an extension that is not loaded has no value to check.
    private function expected()
    {
        $settings = parse_ini_file(__DIR__ . '/../php-test.ini');
        if (!is_array($settings) || ($settings === [])) {
            throw new RuntimeException('php-test.ini does not parse');
        }
        return array_filter($settings, function ($key) {
            return ini_get($key) !== false;
        }, ARRAY_FILTER_USE_KEY);
    }
}
