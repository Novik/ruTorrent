<?php

// The encoding CachedEcho::send() offers is read whether or not one of the
// gzip branches set it, so this drives it over HTTP with an Accept-Encoding
// that names neither: a diagnostic printed here lands ahead of the body and
// takes the Content-Length header with it.
function cachedEchoHttp($accept)
{
    $root = dirname(__DIR__, 2);
    $directory = (getenv('TMPDIR') ?: sys_get_temp_dir())
        . '/cached-echo-encoding-' . getmypid() . '-' . bin2hex(random_bytes(4));
    if (!mkdir($directory, 0700)) throw new RuntimeException('Could not create the fixture directory');
    $process = null;
    try {
        $router = '<?php $phpUseGzip = true; $phpGzipLevel = 2; $profileMask = 0700; $tempDirectory = '
            . var_export($directory, true) . '; require '
            . var_export($root . '/php/utility/cachedecho.php', true) . ';'
            . ' CachedEcho::send(str_repeat("A", 2500), "text/plain");';
        file_put_contents($directory . '/router.php', $router);
        $listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($listener === false) throw new RuntimeException('Could not reserve the HTTP address: ' . $error);
        $address = stream_socket_get_name($listener, false);
        fclose($listener);
        $process = proc_open(array(PHP_BINARY, '-c', $root . '/tests/php-test.ini',
            '-d', 'zlib.output_compression=0', '-S', $address, $directory . '/router.php'),
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
        $peer = stream_socket_client('tcp://' . $address, $errno, $error, 3);
        if ($peer === false) throw new RuntimeException('Could not connect to the HTTP fixture: ' . $error);
        try {
            stream_set_timeout($peer, 5);
            fwrite($peer, "GET / HTTP/1.0\r\nHost: " . $address . "\r\n"
                . (is_null($accept) ? '' : "Accept-Encoding: " . $accept . "\r\n")
                . "Connection: close\r\n\r\n");
            $response = stream_get_contents($peer);
        } finally {
            fclose($peer);
        }
        if (!is_string($response) || strpos($response, "\r\n\r\n") === false)
            throw new RuntimeException('The HTTP fixture sent no complete response');
        list($head, $body) = explode("\r\n\r\n", $response, 2);
        $lines = explode("\r\n", $head);
        array_shift($lines);
        $headers = array();
        foreach ($lines as $line) {
            $colon = strpos($line, ':');
            if ($colon !== false)
                $headers[strtolower(substr($line, 0, $colon))] = trim(substr($line, $colon + 1));
        }
        return array($headers, $body);
    } finally {
        if (is_resource($process)) { proc_terminate($process); proc_close($process); }
        foreach (glob($directory . '/*') as $path) unlink($path);
        rmdir($directory);
    }
}

$failures = 0;
$cases = array(
    'an Accept-Encoding naming no gzip sends the plain body and its length'
        => array('deflate, br', null),
    'an Accept-Encoding naming gzip still compresses' => array('gzip', 'gzip'),
    'a request naming no encoding at all is unchanged' => array(null, null),
);
foreach ($cases as $name => $case) {
    try {
        list($accept, $encoding) = $case;
        list($headers, $body) = cachedEchoHttp($accept);
        if (is_null($encoding) && $body !== str_repeat('A', 2500))
            throw new RuntimeException('The response body is not the content: '
                . substr(str_replace("\n", ' ', $body), 0, 240));
        if (($headers['content-encoding'] ?? null) !== $encoding)
            throw new RuntimeException('Unexpected Content-Encoding: '
                . var_export($headers['content-encoding'] ?? null, true));
        if (is_null($encoding)) {
            if (($headers['content-length'] ?? null) !== '2500')
                throw new RuntimeException('Unexpected Content-Length: '
                    . var_export($headers['content-length'] ?? null, true));
        } else {
            $body = gzdecode($body);
        }
        if ($body !== str_repeat('A', 2500))
            throw new RuntimeException('The response body is not the content: '
                . substr(str_replace("\n", ' ', $body), 0, 220));
        echo 'ok - ', $name, "\n";
    } catch (Throwable $error) {
        $failures++;
        echo 'not ok - ', $name, ': ', $error->getMessage(), "\n";
    }
}
echo count($cases), ' tests, ', $failures, " failures\n";
exit($failures ? 1 : 0);
