<?php

/**
 * rCPU::get() reports the one-minute load as a percentage of the box's cores.
 *
 * Reading the load average is its own step: sys_getloadavg() is missing on some
 * platforms the WebUI runs on, so /proc/loadavg and uptime(1) stand in for it.
 * These tests pin the arithmetic get() does on whatever that step returns, and
 * that the step itself answers on this platform.
 *
 * TestLib is deliberately not used, the way HistoryDataTest avoids it: cpu.php
 * loads the real php/cache.php and its doubles would collide.
 */

$_ENV['RU_PROFILE_PATH'] = sys_get_temp_dir() . '/rutorrent-cpuload-test-' . getmypid();

require_once(__DIR__ . '/../../../plugins/cpuload/cpu.php');

class CpuLoadStub extends rCPU
{
    public $stubLoadavg = array(0, 0, 0);

    protected function loadavg()
    {
        return $this->stubLoadavg;
    }
}

function cpuAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true)
        );
    }
}

$tests = array(
    'the load is reported as a percentage of the cores' => function () {
        $cpu = new CpuLoadStub();
        $cpu->count = 4;
        $cpu->stubLoadavg = array('2.00', '1.00', '0.50');
        cpuAssertSame(50.0, $cpu->get(), 'two busy cores out of four is 50%');
        $cpu->stubLoadavg = array('1.234', '1.00', '0.50');
        cpuAssertSame(31.0, $cpu->get(), 'the percentage is rounded');
        $cpu->count = 1;
        $cpu->stubLoadavg = array('0.00', '0.00', '0.00');
        cpuAssertSame(0.0, $cpu->get(), 'an idle box reads zero');
    },

    'an overloaded box is clamped to 100' => function () {
        $cpu = new CpuLoadStub();
        $cpu->count = 2;
        $cpu->stubLoadavg = array('9.90', '4.00', '2.00');
        cpuAssertSame(100.0, $cpu->get(), 'the meter never goes past full');
    },

    'the load average is read on this platform' => function () {
        // The reader is one method with three sources; whichever one this
        // platform lands on has to produce a number for get() to divide.
        $cpu = new rCPU();
        $method = new ReflectionMethod('rCPU', 'loadavg');
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }
        $loadavg = $method->invoke($cpu);
        if (!is_array($loadavg) || !count($loadavg)) {
            throw new RuntimeException('the load average is not a non-empty array: ' . var_export($loadavg, true));
        }
        if (!is_numeric(trim((string) $loadavg[0]))) {
            throw new RuntimeException('the one-minute load is not a number: ' . var_export($loadavg[0], true));
        }
        $percentage = $cpu->get();
        if (!is_numeric($percentage) || $percentage < 0 || $percentage > 100) {
            throw new RuntimeException('the percentage is out of range: ' . var_export($percentage, true));
        }
    },
);

$failures = 0;
foreach ($tests as $name => $test) {
    try {
        $test();
        echo "ok - {$name}\n";
    } catch (Throwable $error) {
        $failures++;
        echo "not ok - {$name}\n";
        echo '  ' . get_class($error) . ': ' . $error->getMessage() . "\n";
    }
}
echo count($tests) . ' tests, ' . $failures . " failures\n";
exit($failures === 0 ? 0 : 1);
