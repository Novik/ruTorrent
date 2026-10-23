<?php

require_once(__DIR__ . '/TestHttpServer.php');

function cachedEchoNegotiatedResponse($port, $accept, $query)
{
    $peer = stream_socket_client('tcp://127.0.0.1:' . $port, $errno, $error, 2);
    if ($peer === false) throw new RuntimeException('Could not connect to the HTTP fixture: ' . $error);
    try {
        stream_set_timeout($peer, 5);
        $request = 'GET /?' . http_build_query($query) . " HTTP/1.1\r\nHost: 127.0.0.1\r\n"
            . ($accept === null ? '' : 'Accept-Encoding: ' . $accept . "\r\n")
            . "Connection: close\r\n\r\n";
        if (fwrite($peer, $request) !== strlen($request))
            throw new RuntimeException('Could not write the complete HTTP request');
        $response = stream_get_contents($peer);
        if ($response === false || stream_get_meta_data($peer)['timed_out']
            || strpos($response, "\r\n\r\n") === false)
            throw new RuntimeException('The response did not complete successfully');
    } finally {
        fclose($peer);
    }
    list($head, $body) = explode("\r\n\r\n", $response, 2);
    $lines = explode("\r\n", $head);
    if (array_shift($lines) !== 'HTTP/1.1 200 OK')
        throw new RuntimeException('The HTTP fixture did not return 200 OK');
    $headers = array();
    foreach ($lines as $line) {
        $colon = strpos($line, ':');
        if ($colon !== false)
            $headers[strtolower(substr($line, 0, $colon))] = trim(substr($line, $colon + 1));
    }
    return array($headers, $body);
}

$directory = (getenv('TMPDIR') ?: sys_get_temp_dir())
    . '/cached-echo-negotiation-' . getmypid() . '-' . bin2hex(random_bytes(4));
if (!mkdir($directory, 0700)) throw new RuntimeException('Could not create the fixture directory');
$server = null;
$failures = 0;
$cases = array(
    'gzip explicitly refused' => array('gzip;q=0', null),
    'legacy gzip explicitly refused' => array('x-gzip;q=0', null),
    'wildcard does not undo gzip refusal' => array('gzip;q=0, *;q=1', null),
    'wildcard does not undo legacy gzip refusal' => array('x-gzip;q=0, *;q=1', null),
    'zero with fractional digits refuses gzip' => array('gzip;q=0.000', null),
    'zero with an empty fractional part refuses gzip' => array('gzip;q=0.', null),
    'one with an empty fractional part accepts gzip' => array('gzip;q=1.', 'gzip'),
    'positive fractional quality accepts gzip' => array('gzip;q=0.001', 'gzip'),
    'invalid quality cannot fall back to wildcard' => array('gzip;q=bogus, *;q=1', null),
    'negative quality refuses gzip' => array('gzip;q=-1', null),
    'quality greater than one refuses gzip' => array('gzip;q=1.001', null),
    'excess quality precision refuses gzip' => array('gzip;q=0.0001', null),
    'a second quality parameter refuses gzip' => array('gzip;q=1;q=0', null),
    'unsupported parameter refuses gzip' => array('gzip;level=1', null),
    'duplicate gzip cannot undo refusal' => array('gzip;q=0, gzip;q=1', null),
    'duplicate legacy gzip cannot undo refusal' => array('x-gzip;q=0, x-gzip;q=1', null),
    'duplicate coding is refused in reverse order' => array('gzip;q=1, GZIP;q=0', null),
    'repeated positive gzip survives wildcard refusal' => array('gzip, gzip, *;q=0', 'gzip'),
    'repeated positive legacy gzip survives wildcard refusal' => array('x-gzip, x-gzip, *;q=0', 'x-gzip'),
    'repeated fractional gzip is case insensitive' => array('gzip;q=0.5, GZIP;q=0.5, *;q=0', 'gzip'),
    'repeated positive gzip survives identity refusal' => array('gzip, gzip, identity;q=0', 'gzip'),
    'repeated positive quality still accepts gzip' => array('gzip;q=1, gzip;q=1', 'gzip'),
    'duplicate qualities retain the lower weight' => array('gzip;q=0.2, gzip;q=1, x-gzip;q=0.5', 'x-gzip'),
    'duplicate qualities retain the lower weight in reverse order' => array('gzip;q=1, gzip;q=0.2, x-gzip;q=0.5', 'x-gzip'),
    'malformed duplicate quality keeps gzip refused' => array('gzip;q=bogus, gzip;q=1', null),
    'malformed duplicate quality refuses gzip after acceptance' => array('gzip;q=1, gzip;q=bogus', null),
    'repeated positive wildcard accepts gzip' => array('*;q=0.5, *;q=1', 'gzip'),
    'repeated zero wildcard cannot undo refusal' => array('*;q=0, *;q=1', null),
    'explicit gzip overrides wildcard refusal' => array('*;q=0, gzip;q=1', 'gzip'),
    'explicit legacy gzip overrides wildcard refusal' => array('*;q=0, x-gzip;q=1', 'x-gzip'),
    'accepted gzip survives legacy refusal' => array('x-gzip;q=0, gzip;q=1', 'gzip'),
    'accepted legacy gzip survives gzip refusal' => array('gzip;q=0, x-gzip;q=1', 'x-gzip'),
    'higher gzip quality wins' => array('x-gzip;q=0.5, gzip;q=1', 'gzip'),
    'higher legacy gzip quality wins' => array('x-gzip;q=1, gzip;q=0.5', 'x-gzip'),
    'equal quality prefers gzip' => array('x-gzip;q=1, gzip;q=1', 'gzip'),
    'exact gzip is accepted' => array('gzip', 'gzip'),
    'exact legacy gzip is accepted' => array('x-gzip', 'x-gzip'),
    'coding and quality are case insensitive' => array('GZIP;Q=1.000', 'gzip'),
    'optional whitespace is accepted' => array('br,  gzip ; q = 0.5 ', 'gzip'),
    'a coding containing gzip is unsupported' => array('notgzip', null),
    'a coding containing legacy gzip is unsupported' => array('notx-gzip', null),
    'a gzip prefix is unsupported' => array('gzip-extra', null),
    'another coding stays plain' => array('br', null),
    'a wildcard accepts gzip' => array('*', 'gzip'),
    'a zero wildcard stays plain' => array('*;q=0', null),
    'an empty header stays plain' => array('', null),
    'an absent header stays plain' => array(null, null),
    'a body below the threshold stays plain' => array('gzip', null, array('size' => 2047)),
    'a body at the threshold is compressed' => array('gzip', 'gzip', array('size' => 2048)),
    'disabled gzip stays plain' => array('gzip', null, array('gzip' => 0)),
    'compressed response permits explicit caller cleanup' => array('gzip', 'gzip', array('continue' => 1)),
    'refused gzip permits explicit caller cleanup' => array('gzip;q=0', null, array('continue' => 1)),
);
try {
    mkdir($directory . '/answers', 0700);
    $router = '<?php $phpUseGzip = !isset($_GET["gzip"]) || $_GET["gzip"] !== "0"; '
        . '$phpGzipLevel = 2; $profileMask = 0700; $tempDirectory = '
        . var_export($directory . '/answers', true) . '; require '
        . var_export(dirname(__DIR__, 2) . '/php/utility/cachedecho.php', true) . '; '
        . '$body = str_repeat("A", isset($_GET["size"]) ? (int)$_GET["size"] : 2500); '
        . 'if (isset($_GET["continue"])) CachedEcho::send($body, "text/plain", false, false); '
        . 'else CachedEcho::send($body, "text/plain"); '
        . 'file_put_contents(' . var_export($directory . '/continued', true) . ', "yes");';
    file_put_contents($directory . '/router.php', $router);
    $server = TestHttpServer::start($directory, array(),
        array_merge(PhpProcess::sameIni(), array('-d', 'zlib.output_compression=0')),
        $directory . '/router.php');
    foreach ($cases as $name => $case) {
        try {
            list($accept, $encoding) = $case;
            $query = $case[2] ?? array();
            if (is_file($directory . '/continued')) unlink($directory . '/continued');
            list($headers, $body) = cachedEchoNegotiatedResponse($server->port(), $accept, $query);
            if (($headers['content-encoding'] ?? null) !== $encoding)
                throw new RuntimeException('Unexpected Content-Encoding: '
                    . var_export($headers['content-encoding'] ?? null, true));
            $size = $query['size'] ?? 2500;
            if ($encoding === null && ($headers['content-length'] ?? null) !== (string)$size)
                throw new RuntimeException('The plain response has no exact Content-Length');
            if ($encoding !== null) $body = gzdecode($body);
            if ($body !== str_repeat('A', $size))
                throw new RuntimeException('The complete response body changed');
            clearstatcache(true, $directory . '/continued');
            if (is_file($directory . '/continued') !== isset($query['continue']))
                throw new RuntimeException('Unexpected caller continuation after sending');
            if (count(scandir($directory . '/answers')) !== 2)
                throw new RuntimeException('The response left a temporary file behind');
            echo 'ok - ', $name, "\n";
        } catch (Throwable $error) {
            $failures++;
            echo 'not ok - ', $name, ': ', $error->getMessage(), "\n";
        }
    }
    // Startup diagnostics ("in Unknown on line 0") belong to the PHP build, such as
    // the JIT warning hosted runners print when an extension replaces the executor.
    $errors = preg_grep('/PHP (?:Warning|Notice|Deprecated|Fatal|Parse)|Uncaught/',
        explode("\n", file_get_contents($directory . '/server.err')));
    $errors = preg_grep('/ in Unknown on line 0\s*$/', $errors, PREG_GREP_INVERT);
    if ($errors)
        throw new RuntimeException('The HTTP fixture reported a PHP diagnostic: ' . implode("\n", $errors));
} catch (Throwable $error) {
    $failures++;
    echo 'not ok - HTTP fixture: ', $error->getMessage(), "\n";
} finally {
    if ($server !== null) $server->close();
    TestTree::remove($directory);
}
echo count($cases), ' tests, ', $failures, " failures\n";
exit($failures ? 1 : 0);
