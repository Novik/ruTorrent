<?php

require_once(__DIR__ . '/../../php/Snoopy.class.inc');

// Use a real TCP peer in a separate PHP process; no pcntl extension is needed.
// The delay starts after it receives the request, not when the process starts.
function snoopyHeaderTimeoutResponse($first, $late, $delay)
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
        $client = new Snoopy();
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
            'timed_out' => $client->timed_out, 'body' => $client->results);
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
    // Bytes arriving in the second read window must not become a successful body.
    'a late first response line is a timeout' => array('', "HTTP/1.0 200 OK\r\n\r\nlate", 1300000, false),
    'a late next header is a timeout' => array("HTTP/1.0 200 OK\r\n", "X-Late: yes\r\n\r\nlate", 1300000, false),
    'complete headers and body are accepted' => array('', "HTTP/1.0 200 OK\r\nX-Test: yes\r\n\r\ncomplete", 0, true),
);
foreach ($cases as $name => $case) {
    try {
        list($first, $late, $delay, $success) = $case;
        $seen = snoopyHeaderTimeoutResponse($first, $late, $delay);
        $expected = array('fetched' => $success, 'status' => $success ? '200' : -100,
            'timed_out' => !$success, 'body' => $success ? 'complete' : '');
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
