<?php

// A minimal local runner, for the same reason as tests/php/SnoopyTest.php:
// settings.php drags in the real rXMLRPC* classes, which collide with the
// doubles in tests/plugins/rutracker_check/TestLib.php.
require_once(__DIR__ . '/../../php/settings.php');

function scheduleAssertTrue($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function scheduleAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true)
        );
    }
}

// The instant a registration made at $now would fire.
function scheduleFiresAt($name, $interval, $now)
{
    return $now + rTorrentSettings::getAlignedStart($name, $interval, $now);
}

$hourly = 3600;
$daily = 86400;
$quarterMinute = 15;

$tests = array(
    'reloads do not move the fire time' => function () use ($hourly) {
        $now = 1755200000;
        $first = scheduleFiresAt('ratio', $hourly, $now);

        for ($reload = $now; $reload < $first; $reload += 137) {
            scheduleAssertSame(
                $first,
                scheduleFiresAt('ratio', $hourly, $reload),
                'Re-registering ' . ($reload - $now) . 's later moved the fire time'
            );
        }
    },
    'intervals longer than an hour are stable too' => function () use ($daily) {
        $now = 1755200000;
        $first = scheduleFiresAt('loginmgr', $daily, $now);

        for ($reload = $now; $reload < $first; $reload += 3607) {
            scheduleAssertSame(
                $first,
                scheduleFiresAt('loginmgr', $daily, $reload),
                'Re-registering ' . ($reload - $now) . 's later moved the daily fire time'
            );
        }
    },
    'intervals shorter than a minute are stable too' => function () use ($quarterMinute) {
        $now = 1755200000;
        $first = scheduleFiresAt('erasedata', $quarterMinute, $now);

        for ($reload = $now; $reload < $first; $reload++) {
            scheduleAssertSame(
                $first,
                scheduleFiresAt('erasedata', $quarterMinute, $reload),
                'Re-registering ' . ($reload - $now) . 's later moved the 15s fire time'
            );
        }
    },
    'a task that already fired moves to the next slot, not a fresh interval' => function () use ($hourly) {
        $now = 1755200000;
        $first = scheduleFiresAt('ratio', $hourly, $now);

        scheduleAssertSame(
            $first + $hourly,
            scheduleFiresAt('ratio', $hourly, $first + 1),
            'The slot after a fire is not one interval later'
        );
        scheduleAssertSame(
            $first + $hourly,
            scheduleFiresAt('ratio', $hourly, $first + $hourly - 1),
            'A reload just before the next fire moved it'
        );
    },
    'the task keeps firing on its own period' => function () use ($hourly) {
        $now = 1755200000;
        $first = scheduleFiresAt('scheduler', $hourly, $now);
        $next = scheduleFiresAt('scheduler', $hourly, $first + 1);

        scheduleAssertSame($first + $hourly, $next, 'The period drifted after the task fired');
    },
    'a reload never asks for an immediate run' => function () use ($hourly) {
        for ($second = 0; $second < 120; $second++) {
            $start = rTorrentSettings::getAlignedStart('autowatch', $hourly, 1755200000 + $second * 29);
            scheduleAssertTrue(
                $start >= 1 && $start <= $hourly,
                "Start {$start} is outside 1..{$hourly}, so a reload could fire the task at once"
            );
        }
    },
    'each task gets its own slot within the jitter window' => function () use ($hourly) {
        global $schedule_rand;
        $schedule_rand = 10;

        $offsets = array();
        foreach (array('ratio', 'scheduler', 'loginmgr', 'autowatch', 'erasedata') as $name) {
            $offsets[$name] = scheduleFiresAt($name, $hourly, 1755200000) % $hourly;
        }

        foreach ($offsets as $name => $offset) {
            scheduleAssertTrue(
                $offset % $hourly <= $schedule_rand || $hourly - ($offset % $hourly) <= $schedule_rand,
                "{$name} landed at offset {$offset}, outside the jitter window"
            );
            scheduleAssertSame(
                $offset,
                scheduleFiresAt($name, $hourly, 1755200000 + 900) % $hourly,
                "{$name} did not keep its slot across a reload"
            );
        }
        scheduleAssertTrue(count(array_unique($offsets)) > 1, 'Every task landed on the same second');
    },
);

$failures = 0;
foreach ($tests as $name => $callback) {
    try {
        $callback();
        echo "ok - {$name}\n";
    } catch (Throwable $error) {
        $failures++;
        echo "not ok - {$name}\n";
        echo '  ' . get_class($error) . ': ' . $error->getMessage() . "\n";
    }
}
echo count($tests) . ' tests, ' . $failures . " failures\n";

exit($failures === 0 ? 0 : 1);
