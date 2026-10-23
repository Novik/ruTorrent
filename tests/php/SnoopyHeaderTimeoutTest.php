<?php

require_once(__DIR__ . '/../../php/Snoopy.class.inc');

// Use a real TCP peer in a separate PHP process; no pcntl extension is needed.
// The delay starts after it receives the request, not when the process starts.
function snoopyTimeoutResponse($client, $first, $late, $delay)
{
    $worker = <<<'PHP'
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($server === false) {
    fwrite(STDERR, 'listen: ' . $error);
    exit(1);
}
echo stream_socket_get_name($server, false), "\n";
flush();
$peer = stream_socket_accept($server, 5);
if ($peer === false) exit(2);
stream_set_timeout($peer, 5);
do {
    $line = fgets($peer);
    if ($line === false) exit(3);
} while ($line !== "\r\n");
$first = base64_decode($argv[1]);
if ($first !== '') fwrite($peer, $first);
usleep((int) $argv[3]);
// A correct client has already closed on timeout; that is an expected write failure.
@fwrite($peer, base64_decode($argv[2]));
fclose($peer);
fclose($server);
PHP;
    $process = proc_open(
        array(PHP_BINARY, '-c', __DIR__ . '/../php-test.ini', '-r', $worker,
            base64_encode($first), base64_encode($late), (string) $delay),
        array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
        $pipes
    );
    if (!is_resource($process)) throw new RuntimeException('Could not start the HTTP peer');
    $socket = false;
    try {
        fclose($pipes[0]);
        stream_set_timeout($pipes[1], 5);
        $address = fgets($pipes[1]);
        if ($address === false) throw new RuntimeException('The HTTP peer did not announce its address');
        $socket = stream_socket_client('tcp://' . trim($address), $errno, $error, 5);
        if ($socket === false) throw new RuntimeException('Could not connect to the HTTP peer: ' . $error);
        $client->read_timeout = 1;
        $client->host = '127.0.0.1';
        $fetched = $client->_httprequest('/', $socket, 'http://' . trim($address) . '/', 'GET');
        fclose($socket);
        $socket = false;
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        $process = null;
        if ($exit !== 0 || $stderr !== '')
            throw new RuntimeException('HTTP peer exited ' . $exit . ': ' . $stderr);
        return array('fetched' => $fetched, 'status' => $client->status,
            'timed_out' => $client->timed_out, 'body' => $client->results, 'error' => $client->error);
    } finally {
        if (is_resource($socket)) fclose($socket);
        if (is_resource($process)) {
            proc_terminate($process);
            foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
            proc_close($process);
        }
    }
}

$failures = 0;
$cases = array(
    // A failed second read must not leave the first response's body available.
    'a late first response line clears the previous body' => array('', "HTTP/1.0 200 OK\r\n\r\nlate", 1300000,
        array(false, -100, true, '', '')),
    'a late next header clears the previous body' => array("HTTP/1.0 200 OK\r\n", "X-Late: yes\r\n\r\nlate", 1300000,
        array(false, -100, true, '', '')),
    // fgets() can return a partial line with its timeout flag already set.
    'a timed out partial header clears the previous body' => array('HTTP/1.0 200', " OK\r\n\r\nlate", 1300000,
        array(false, -100, true, '', '')),
    'a timed out body clears the previous body' => array("HTTP/1.0 200 OK\r\n\r\n", 'late', 1300000,
        array(false, -100, true, '', '')),
    'a second complete response replaces the previous body' => array('', "HTTP/1.0 200 OK\r\nX-Test: yes\r\n\r\ncomplete", 0,
        array(true, '200', false, 'complete', '')),
    'a second empty response replaces the previous body' => array('', "HTTP/1.0 200 OK\r\n\r\n", 0,
        array(true, '200', false, '', '')),
    'an oversized response keeps its distinct refusal' => array('', "HTTP/1.0 200 OK\r\n\r\noversized", 0,
        array(false, -101, false, '', 'oversized-response-body')),
    // Also pin body-read admission on a fresh client, independently of reuse cleanup.
    'a timed out partial body is refused' => array("HTTP/1.0 200 OK\r\n\r\ncomp", 'lete', 1300000,
        array(false, -100, true, '', ''), false),
    'a fragmented body arriving before timeout is accepted' => array("HTTP/1.0 200 OK\r\n\r\ncomp", 'lete', 100000,
        array(true, '200', false, 'complete', ''), false),
);
foreach ($cases as $name => $case) {
    try {
        $client = new Snoopy();
        $client->maxlength = 8;
        if ($case[4] ?? true) {
            $seed = snoopyTimeoutResponse($client, '', "HTTP/1.0 200 OK\r\n\r\nprevious", 0);
            $seedExpected = array('fetched' => true, 'status' => '200', 'timed_out' => false,
                'body' => 'previous', 'error' => '');
            if ($seed !== $seedExpected)
                throw new RuntimeException('The first response must seed a real successful body: ' . json_encode($seed));
        }
        list($first, $late, $delay, $want) = $case;
        $seen = snoopyTimeoutResponse($client, $first, $late, $delay);
        $expected = array_combine(array('fetched', 'status', 'timed_out', 'body', 'error'), $want);
        if ($seen !== $expected)
            throw new RuntimeException('expected ' . json_encode($expected) . ', got ' . json_encode($seen));
        echo 'ok - ', $name, "\n";
    } catch (Throwable $error) {
        $failures++;
        echo 'not ok - ', $name, ': ', $error->getMessage(), "\n";
    }
}
echo count($cases) - $failures, ' passed, ', $failures, " failed\n";
exit($failures ? 1 : 0);
