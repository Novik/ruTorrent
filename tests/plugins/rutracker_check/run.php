<?php

$tests = array(
    __DIR__ . '/CheckerTest.php',
    __DIR__ . '/RuTrackerHandlerTest.php',
    __DIR__ . '/NNMClubHandlerTest.php',
    __DIR__ . '/../../php/SnoopyTest.php',
);
$failures = 0;

foreach($tests as $test) {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($test);
    passthru($command, $status);
    if ($status !== 0) $failures++;
}

exit($failures === 0 ? 0 : 1);
