<?php

// Deliberately not using tests/plugins/rutracker_check/TestLib.php here:
// history.php transitively loads php/Snoopy.class.inc and php/settings.php,
// whose real classes collide with TestLib's doubles in either require order.
// A minimal local runner keeps the real classes intact, the same way
// tests/php/SnoopyTest.php does.
require_once(__DIR__ . '/../../../plugins/history/history.php');

function historyAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true)
        );
    }
}

// A record shaped like the ones update.php builds, keyed the way add() keys
// them. Built by hand so a test can place one straight into $data without
// going through add(), which would try to write the cache file.
function historyRecord($key, $actionTime, $action = 1)
{
    return array(
        'action' => $action,
        'name' => 'torrent-' . $key,
        'action_time' => $actionTime,
        'hash' => $key,
    );
}

// A history object holding exactly $records, with no pending changes: this is
// what a process has right after load().
function historyLoaded($records)
{
    $data = new rHistoryData();
    $data->data = $records;
    return $data;
}

// Replays what a writer does between load() and store() without touching the
// filesystem: add() and delete() both call store(), which this test has no
// cache for, so the two bookkeeping arrays are filled through reflection.
function historyRecordAddition($object, $record, $limit)
{
    $object->data[$record['hash']] = $record;
    historySetProtected($object, 'ownAdditions',
        historyGetProtected($object, 'ownAdditions') + array($record['hash'] => $record));
    historySetProtected($object, 'limit', $limit);
}

function historyRecordRemoval($object, $key)
{
    unset($object->data[$key]);
    historySetProtected($object, 'ownRemovals',
        historyGetProtected($object, 'ownRemovals') + array($key => true));
}

function historyGetProtected($object, $property)
{
    $reflection = new ReflectionProperty('rHistoryData', $property);
    if (PHP_VERSION_ID < 80100) {
        $reflection->setAccessible(true);
    }
    return $reflection->getValue($object);
}

function historySetProtected($object, $property, $value)
{
    $reflection = new ReflectionProperty('rHistoryData', $property);
    if (PHP_VERSION_ID < 80100) {
        $reflection->setAccessible(true);
    }
    $reflection->setValue($object, $value);
}

$tests = array(
    // The live failure: three events land in the same second when a torrent is
    // replaced, each in its own process, and the last writer used to publish a
    // file without the rows the others had added.
    'a row another process added while this one was writing survives' => function () {
        $ours = historyLoaded(array('a' => historyRecord('a', 100)));
        historyRecordAddition($ours, historyRecord('b', 101), 500);

        // Meanwhile a second process recorded its own event and stored it.
        $onDisk = historyLoaded(array(
            'a' => historyRecord('a', 100),
            'c' => historyRecord('c', 102),
        ));

        historyAssertSame(true, $ours->merge($onDisk, null), 'merge reports success');
        historyAssertSame(array('c', 'b', 'a'), array_keys($ours->data),
            'both writers keep their row, newest first');
    },

    'a row this process deleted does not come back from the fresher copy' => function () {
        $ours = historyLoaded(array(
            'a' => historyRecord('a', 100),
            'b' => historyRecord('b', 101),
        ));
        historyRecordRemoval($ours, 'b');

        $onDisk = historyLoaded(array(
            'a' => historyRecord('a', 100),
            'b' => historyRecord('b', 101),
        ));

        $ours->merge($onDisk, null);
        historyAssertSame(array('a'), array_keys($ours->data),
            'the deletion is replayed on top of the fresher copy');
    },

    'a delete never trims a history longer than the default cap' => function () {
        $records = array();
        for ($i = 0; $i < 600; $i++) {
            $records['k' . $i] = historyRecord('k' . $i, 1000 + $i);
        }
        $ours = historyLoaded($records);
        historyRecordRemoval($ours, 'k0');

        $ours->merge(historyLoaded($records), null);
        historyAssertSame(599, count($ours->data),
            'a delete carries no configured limit and must not impose the default one');
    },

    'a recorded event still applies the configured cap' => function () {
        $records = array();
        for ($i = 0; $i < 10; $i++) {
            $records['k' . $i] = historyRecord('k' . $i, 1000 + $i);
        }
        $ours = historyLoaded($records);
        historyRecordAddition($ours, historyRecord('new', 2000), 4);

        $ours->merge(historyLoaded($records), null);
        historyAssertSame(2, count($ours->data), 'over the cap, half of it is kept');
        historyAssertSame('new', array_keys($ours->data)[0], 'the newest row is kept');
    },

    // rTorrent names a magnet that has no metadata yet <INFOHASH>.meta and
    // replaces that placeholder with the real download the moment metadata
    // arrives, so every magnet the user adds logs an arrival and a removal
    // nobody asked for. The hash is hex, so the pattern is case-insensitive on
    // the letters and anchored on both ends: only a name that IS a placeholder
    // qualifies, never one that merely looks related.
    'magnet placeholders are recognised, real downloads are not' => function () {
        $placeholders = array(
            'an uppercase placeholder' => str_repeat('A', 40) . '.meta',
            'a lowercase placeholder' => str_repeat('b', 40) . '.meta',
            'a mixed-case placeholder' => str_repeat('cD', 20) . '.meta',
        );
        foreach ($placeholders as $label => $name)
            historyAssertSame(true, rHistoryData::isMagnetPlaceholder($name), $label . ' must be recognised');

        $real = array(
            'a normal download' => 'Some Release 1080p',
            'a name that merely ends in .meta' => 'metadata.meta',
            'a hash-named file with another extension' => str_repeat('A', 40) . '.mkv',
            'a hash-named directory with no extension' => str_repeat('A', 40),
            'a placeholder name with something appended' => str_repeat('A', 40) . '.meta.part',
            'a name one character short of a hash' => str_repeat('A', 39) . '.meta',
            'a name with a non-hex character' => str_repeat('A', 39) . 'Z.meta',
        );
        foreach ($real as $label => $name)
            historyAssertSame(false, rHistoryData::isMagnetPlaceholder($name), $label . ' must be kept');
    },

    'the stored format carries nothing but what it always carried' => function () {
        $ours = historyLoaded(array('a' => historyRecord('a', 100)));
        historyRecordAddition($ours, historyRecord('b', 101), 500);

        $restored = unserialize(serialize($ours));
        historyAssertSame(array('hash', 'modified', 'data'), $ours->__sleep(),
            'only the three long-standing properties are serialised');
        historyAssertSame(array('a', 'b'), array_keys($restored->data),
            'the records survive a round trip');
        historyAssertSame(array(), historyGetProtected($restored, 'ownAdditions'),
            'bookkeeping does not outlive the process that did the writing');
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
