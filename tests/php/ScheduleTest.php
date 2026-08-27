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

// One registration of $name at $now, as both the caller (the &$startAt
// out-parameter) and rTorrent (the serialized command) see it. Driving $now
// rather than reading the clock is the whole point: the promise is that two
// registrations made at *different* instants resolve to the same absolute fire
// time, and the seconds where a broken implementation gives itself away are a
// handful out of an interval, so sampling the real clock would only reach them
// by luck.
function scheduleCommandAt($name, $intervalMinutes, $now)
{
    $startAt = 0;
    $command = rTorrentSettings::get()->getScheduleCommand(
        $name, $intervalMinutes, 'print=noop', $startAt, $now
    );
    return array(
        'now' => $now,
        'startAt' => $startAt,
        'firesAt' => $now + $startAt,
        'key' => $command->params[0]->value,
        'reported' => $command->params[1]->value,
        'interval' => $command->params[2]->value,
    );
}

// The second within an interval that $name's key claims for itself.
function scheduleJitterOffset($name)
{
    global $schedule_rand;
    return abs(crc32($name . User::getUser())) % ($schedule_rand + 1);
}

$hourly = 3600;
$daily = 86400;
$quarterMinute = 15;

// conf/config.php's default, pinned here so the offsets the tests compute are
// the ones the code computes.
$schedule_rand = 10;

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
    'a reload inside the jitter window does not move a getScheduleCommand fire time' => function () {
        global $schedule_rand;

        // The window this case exists to walk runs from the interval boundary
        // up to the key's own second within the jitter spread, so it needs a
        // key whose offset is not 0 or there is nothing between the two to
        // walk. getScheduleCommand's own callers cannot supply one -- 'trafic'
        // and 'rss' both happen to hash to 0 -- so 'ratio' is borrowed here for
        // its offset of 9; the alignment is a pure function of the key, so any
        // key exercises the same code.
        $name = 'ratio';
        $offset = scheduleJitterOffset($name);
        scheduleAssertTrue($offset > 0, "{$name} hashes to offset 0, so this test walks an empty window");

        $intervalMinutes = 60;
        $interval = $intervalMinutes * 60;
        $boundary = 1755198000;                 // a multiple of $interval, so the slot is $boundary+$offset
        scheduleAssertSame(0, $boundary % $interval, 'The chosen instant is not an interval boundary');
        $slot = $boundary + $offset;

        // Every second from just before the boundary to the far end of the
        // jitter spread. Up to the slot the answer must be the slot; from the
        // slot on it must be the next one, one whole interval later and not a
        // fresh countdown. An implementation that jumps to the *next* boundary
        // as soon as this one passes -- the behaviour the deterministic
        // alignment replaced -- reports slot+$interval for the seconds in
        // between.
        for ($now = $boundary - 1; $now <= $boundary + $schedule_rand; $now++) {
            $sample = scheduleCommandAt($name, $intervalMinutes, $now);
            $expected = ($now < $slot) ? $slot : $slot + $interval;
            scheduleAssertSame(
                $expected,
                $sample['firesAt'],
                'A reload at boundary' . sprintf('%+d', $now - $boundary)
                . 's fires at boundary' . sprintf('%+d', $sample['firesAt'] - $boundary)
                . 's instead of boundary' . sprintf('%+d', $expected - $boundary) . 's'
            );
            scheduleAssertSame(
                (string) $sample['startAt'],
                $sample['reported'],
                'A reload at boundary' . sprintf('%+d', $now - $boundary)
                . 's told rTorrent a start the caller was never given'
            );
            scheduleAssertSame((string) $interval, $sample['interval'], 'The interval reached rTorrent in minutes');
            scheduleAssertTrue(
                $sample['startAt'] >= 1 && $sample['startAt'] <= $interval,
                "Start {$sample['startAt']} is outside 1..{$interval}, so a reload could fire the task at once"
            );
        }
    },
    'a getScheduleCommand fire time holds for a whole interval of reloads' => function () {
        $name = 'ratio';
        $intervalMinutes = 60;
        $interval = $intervalMinutes * 60;
        $slot = 1755198000 + scheduleJitterOffset($name);

        // The slot is reached from anywhere in the interval that precedes it,
        // one second at a time; the second the slot itself arrives belongs to
        // the following one.
        for ($now = $slot - $interval; $now < $slot; $now++) {
            scheduleAssertSame(
                $slot,
                scheduleCommandAt($name, $intervalMinutes, $now)['firesAt'],
                'A reload ' . ($slot - $now) . 's before the slot moved it'
            );
        }
        scheduleAssertSame(
            $slot + $interval,
            scheduleCommandAt($name, $intervalMinutes, $slot)['firesAt'],
            'The slot after a fire is not one interval later'
        );
    },
    'each scheduled task gets its own getScheduleCommand slot' => function () {
        global $schedule_rand;

        $intervalMinutes = 5;
        $interval = $intervalMinutes * 60;
        $now = 1755198000;
        $offsets = array();
        foreach (array('trafic', 'rss', 'ratio', 'loginmgr', 'scheduler') as $name) {
            $sample = scheduleCommandAt($name, $intervalMinutes, $now);
            $offsets[$name] = $sample['firesAt'] % $interval;

            scheduleAssertSame(
                scheduleJitterOffset($name),
                $offsets[$name],
                "{$name} did not land on the slot its name picks out"
            );
            scheduleAssertSame(
                $offsets[$name],
                scheduleCommandAt($name, $intervalMinutes, $now + 137)['firesAt'] % $interval,
                "{$name} did not keep its slot across a reload"
            );
            scheduleAssertSame($name . User::getUser(), $sample['key'], "{$name} registered under the wrong key");
        }
        scheduleAssertTrue(count(array_unique($offsets)) > 1, 'Every task landed on the same second');
        scheduleAssertTrue(max($offsets) <= $schedule_rand, 'A task landed outside the jitter window');
    },
    'the clock seam is optional' => function () {
        // Production callers -- plugins/trafic/init.php and
        // plugins/rss/rss.php -- pass four arguments and get time(). Retried
        // until the second holds still across the call, since a tick
        // underneath it would move the answer for an honest reason.
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $startAt = 0;
            $now = time();
            $command = rTorrentSettings::get()->getScheduleCommand('ratio', 60, 'print=noop', $startAt);
            if (time() !== $now) {
                continue;
            }
            scheduleAssertSame(
                (string) $startAt,
                $command->params[1]->value,
                'The out-parameter and the command disagree'
            );
            scheduleAssertSame(
                scheduleCommandAt('ratio', 60, $now)['firesAt'],
                $now + $startAt,
                'Reading the clock and being handed the same instant give different answers'
            );
            return;
        }
        throw new RuntimeException('The clock ticked through every attempt at a stable call');
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
