<?php

// The two refusals in Requests are plain text, and a browser is only told so
// if the answer says so: without a type of its own each inherits the server's
// default_mimetype and is read as html. Drive them over HTTP; a header() call
// under the CLI SAPI would not be a MIME test at all.
function requestsResponse($address, $method, $extra)
{
    $peer = stream_socket_client('tcp://' . $address, $errno, $error, 3);
    if ($peer === false) throw new RuntimeException('Could not connect to the HTTP fixture: ' . $error);
    try {
        stream_set_timeout($peer, 5);
        fwrite($peer, $method . " / HTTP/1.0\r\nHost: " . $address . "\r\nContent-Length: 0\r\n"
            . $extra . "Connection: close\r\n\r\n");
        $response = stream_get_contents($peer);
    } finally {
        fclose($peer);
    }
    if (!is_string($response) || strpos($response, "\r\n\r\n") === false)
        throw new RuntimeException('The HTTP fixture sent no complete response');
    list($head, $body) = explode("\r\n\r\n", $response, 2);
    $lines = explode("\r\n", $head);
    $status = array_shift($lines);
    $headers = array();
    foreach ($lines as $line) {
        $colon = strpos($line, ':');
        if ($colon !== false)
            $headers[strtolower(substr($line, 0, $colon))] = trim(substr($line, $colon + 1));
    }
    return array($status, $headers, $body);
}

function requestsWithServer($check)
{
    $root = dirname(__DIR__, 2);
    $directory = (getenv('TMPDIR') ?: sys_get_temp_dir())
        . '/requests-plain-' . getmypid() . '-' . bin2hex(random_bytes(4));
    if (!mkdir($directory, 0700)) throw new RuntimeException('Could not create the fixture directory');
    $process = null;
    try {
        $router = '<?php $enableCSRFCheck = true; $enabledOrigins = array(); require '
            . var_export($root . '/php/utility/requests.php', true) . ';'
            . ' Requests::disableUnsupportedMethods(); Requests::makeCSRFCheck(); echo "ALLOWED";';
        file_put_contents($directory . '/router.php', $router);
        $listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($listener === false) throw new RuntimeException('Could not reserve the HTTP address: ' . $error);
        $address = stream_socket_get_name($listener, false);
        fclose($listener);
        $process = proc_open(array(PHP_BINARY, '-c', $root . '/tests/php-test.ini',
            '-S', $address, $directory . '/router.php'),
            array(0 => array('file', '/dev/null', 'r'),
                1 => array('file', $directory . '/server.log', 'a'),
                2 => array('file', $directory . '/server.log', 'a')), $pipes, $directory);
        if (!is_resource($process)) throw new RuntimeException('Could not start the HTTP fixture');
        $ready = false;
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $peer = @stream_socket_client('tcp://' . $address, $errno, $error, 0.05);
            if ($peer !== false) { fclose($peer); $ready = true; break; }
            if (!proc_get_status($process)['running']) break;
            usleep(10000);
        }
        if (!$ready) throw new RuntimeException('The HTTP fixture did not start: '
            . file_get_contents($directory . '/server.log'));
        $check($address);
    } finally {
        if (is_resource($process)) { proc_terminate($process); proc_close($process); }
        foreach (glob($directory . '/*') as $path) unlink($path);
        rmdir($directory);
    }
}

$failures = 0;
requestsWithServer(function($address) use (&$failures) {
    $cases = array(
        'an unsupported method is refused as plain text' => array('PUT', '', ' 405 ', 'Method Not Allowed'),
        'a cross-origin POST is refused as plain text' => array('POST', '', ' 403 ', 'Forbidden'),
        'a same-origin POST is still allowed' => array('POST', "Origin: http://" . $address . "\r\n", ' 200 ', 'ALLOWED'),
        'a GET is still allowed' => array('GET', '', ' 200 ', 'ALLOWED'),
    );
    foreach ($cases as $name => $case) {
        try {
            list($method, $extra, $expectedStatus, $expectedBody) = $case;
            list($status, $headers, $body) = requestsResponse($address, $method, $extra);
            if (strpos($status, $expectedStatus) === false)
                throw new RuntimeException('Unexpected HTTP status: ' . $status);
            $type = strtolower($headers['content-type'] ?? '');
            $expectedType = $expectedStatus === ' 200 ' ? 'text/html; charset=utf-8' : 'text/plain; charset=utf-8';
            if ($type !== $expectedType)
                throw new RuntimeException('Unexpected response MIME: ' . $type);
            if ($body !== $expectedBody)
                throw new RuntimeException('Unexpected body: ' . var_export($body, true));
            echo 'ok - ', $name, "\n";
        } catch (Throwable $error) {
            $failures++;
            echo 'not ok - ', $name, ': ', $error->getMessage(), "\n";
        }
    }
    echo count($cases), ' tests, ', $failures, " failures\n";
});
exit($failures ? 1 : 0);
