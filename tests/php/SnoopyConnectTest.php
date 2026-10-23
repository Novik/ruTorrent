<?php

namespace SnoopyConnectFixture;

// Stub only the OS boundary; connect() and its error handling are production code.
function fsockopen($host, $port, &$errno, &$error, $timeout)
{
    $GLOBALS['connectCall'] = 'fsockopen';
    $errno = $GLOBALS['connectErrno'];
    $error = $GLOBALS['connectError'];
    return false;
}

function stream_socket_client($address, &$errno, &$error, $timeout, $flags, $context)
{
    $GLOBALS['connectCall'] = 'stream_socket_client';
    $errno = $GLOBALS['connectErrno'];
    $error = $GLOBALS['connectError'];
    return false;
}
$source = file_get_contents(__DIR__ . '/../../php/Snoopy.class.inc');
$start = strpos($source, 'class Snoopy');
if ($start === false) throw new \RuntimeException('Production class not found');
eval('namespace SnoopyConnectFixture; ' . substr($source, $start));

function connectAssert($condition, $message)
{
    if (!$condition) throw new \RuntimeException($message);
}

function connectFailure($errno, $error, $expectedStatus, $expectedError)
{
    foreach (array('' => 'fsockopen', '127.0.0.1' => 'stream_socket_client') as $bind => $call) {
        $GLOBALS['connectErrno'] = $errno;
        $GLOBALS['connectError'] = $error;
        $GLOBALS['connectCall'] = null;
        $client = new \SnoopyConnectFixture\Snoopy();
        $client->IP = $bind === '' ? null : $bind;
        $client->status = 200;
        connectAssert($client->connect() === false, 'The connection must fail');
        connectAssert($GLOBALS['connectCall'] === $call, 'The chosen OS boundary must execute');
        connectAssert($client->status === $expectedStatus, $call . ' errno ' . $errno . ' must have a transport status');
        connectAssert($client->error === $expectedError, $call . ' errno ' . $errno . ' must retain its diagnostic category');
    }
}

$tests = array(
    'positive errno is never an HTTP status or another negative failure' => function () {
        foreach (array(110, 111, 100, 101, 10061) as $errno) {
            connectFailure($errno, 'Controlled socket failure', -102, 'connection failed (' . $errno . ')');
        }
    },
    'zero and legacy negative errno preserve their categories' => function () {
        foreach (array(0 => 'connection failed (0)', -3 => 'socket creation failed (-3)',
            -4 => 'dns lookup failure (-4)', -5 => 'connection refused or timed out (-5)') as $errno => $message) {
            connectFailure($errno, 'Controlled socket failure', $errno, $message);
        }
    },
    'errno zero recognizes only the PHP resolver prefix without exposing raw text' => function () {
        connectFailure(0, 'php_network_getaddresses: getaddrinfo for private-host.test failed: Name or service not known',
            0, 'dns lookup failure (0)');
        connectFailure(0, 'Unclassified failure mentioning php_network_getaddresses: private-host.test',
            0, 'connection failed (0)');
        connectFailure(0, '', 0, 'connection failed (0)');
    },
);
$failures = 0;
foreach ($tests as $name => $test) {
    try {
        $test();
        echo 'ok - ' . $name . "\n";
    } catch (\Throwable $error) {
        ++$failures;
        echo 'not ok - ' . $name . ': ' . $error->getMessage() . "\n";
    }
}
echo count($tests) . ' tests, ' . $failures . " failures\n";
exit($failures ? 1 : 0);
