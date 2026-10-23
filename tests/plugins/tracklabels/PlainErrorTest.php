<?php

function tracklabelsResponse($address, $field, $value)
{
    $peer = stream_socket_client('tcp://' . $address, $errno, $error, 3);
    if ($peer === false) throw new RuntimeException('Could not connect to the HTTP fixture: ' . $error);
    try {
        stream_set_timeout($peer, 3);
        $query = http_build_query(array($field => $value), '', '&', PHP_QUERY_RFC3986);
        fwrite($peer, "GET /?" . $query . " HTTP/1.0\r\nHost: " . $address . "\r\nConnection: close\r\n\r\n");
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

// Run the real action and utilities under an HTTP SAPI; CLI header() is not a MIME test.
function tracklabelsWithServer($check)
{
    $root = dirname(__DIR__, 3);
    $directory = (getenv('TMPDIR') ?: sys_get_temp_dir())
        . '/tracklabels-error-' . getmypid() . '-' . bin2hex(random_bytes(4));
    if (!mkdir($directory, 0700)) throw new RuntimeException('Could not create the fixture directory');
    $process = null;
    try {
        mkdir($directory . '/profile/settings/labels', 0700, true);
        $image = file_get_contents($root . '/plugins/tracklabels/trackers/unknown.png');
        if ($image === false) throw new RuntimeException('Could not read the fixture PNG');
        file_put_contents($directory . '/profile/settings/labels/safe.png', $image);
        $router = '<?php $_ENV["RU_PROFILE_PATH"] = '
            . var_export($directory . '/profile', true) . '; $_ENV["RU_LOG_FILE"] = '
            . var_export($directory . '/application.log', true) . '; require '
            . var_export($root . '/plugins/tracklabels/action.php', true) . ';';
        file_put_contents($directory . '/router.php', $router);
        $listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($listener === false) throw new RuntimeException('Could not reserve the HTTP address: ' . $error);
        $address = stream_socket_get_name($listener, false);
        fclose($listener);
        $process = proc_open(array(PHP_BINARY, '-c', $root . '/tests/php-test.ini',
            '-S', $address, $directory . '/router.php'), array(
            0 => array('file', '/dev/null', 'r'),
            1 => array('file', $directory . '/server.log', 'a'),
            2 => array('file', $directory . '/server.log', 'a')), $pipes,
            $root . '/plugins/tracklabels');
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
        $check($address, $image);
    } finally {
        if (is_resource($process)) {
            proc_terminate($process);
            proc_close($process);
        }
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory,
            FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file)
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($directory);
    }
}

$failures = 0;
tracklabelsWithServer(function($address, $image) use (&$failures) {
    $cases = array(
        'an invalid label is plain text even when it contains markup' => array('label', '../<b>sample</b>'),
        'an invalid tracker is plain text even when it contains markup' => array('tracker', '../<b>sample</b>'),
        'a valid label still returns the exact PNG' => array('label', 'safe'),
    );
    foreach ($cases as $name => $case) {
        try {
            list($field, $value) = $case;
            list($status, $headers, $body) = tracklabelsResponse($address, $field, $value);
            $valid = $value === 'safe';
            if (strpos($status, $valid ? ' 200 ' : ' 400 ') === false)
                throw new RuntimeException('Unexpected HTTP status: ' . $status);
            $type = strtolower($headers['content-type'] ?? '');
            if ($type !== ($valid ? 'image/png' : 'text/plain; charset=utf-8'))
                throw new RuntimeException('Unexpected response MIME: ' . $type);
            if (!$valid && ($headers['x-content-type-options'] ?? '') !== 'nosniff')
                throw new RuntimeException('The refusal does not forbid sniffing');
            $expected = $valid ? $image : 'Invalid ' . $field . ': ' . $value;
            if ($body !== $expected)
                throw new RuntimeException('The response bytes changed');
            echo 'ok - ', $name, "\n";
        } catch (Throwable $error) {
            $failures++;
            echo 'not ok - ', $name, ': ', $error->getMessage(), "\n";
        }
    }
});
echo '3 tests, ', $failures, " failures\n";
exit($failures ? 1 : 0);
