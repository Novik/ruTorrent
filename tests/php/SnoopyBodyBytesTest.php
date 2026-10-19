<?php

require_once(__DIR__ . '/../../php/Snoopy.class.inc');

function bodyBytesAssert($condition, $message)
{
    if (!$condition) throw new RuntimeException($message);
}

// Use a real HTTP peer and the installed curl: a scripted curl would not
// exercise -d's interpretation of a leading @ as a local filename.
function bodyBytesRequest($curl, $body, $response = 'ok')
{
    $peer = <<<'PHP'
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($server === false) exit(2);
fwrite(STDOUT, stream_socket_get_name($server, false) . "\n");
fflush(STDOUT);
$socket = @stream_socket_accept($server, 5);
if ($socket === false) exit(3);
stream_set_timeout($socket, 5);
$headers = '';
while (($line = fgets($socket)) !== false && $line !== "\r\n") $headers .= $line;
$length = preg_match('/^Content-Length: *([0-9]+)\r?$/mi', $headers, $match) ? (int)$match[1] : 0;
$body = '';
while (strlen($body) < $length) {
    $part = fread($socket, $length - strlen($body));
    if ($part === false || $part === '') exit(4);
    $body .= $part;
}
$answer = $argv[1];
fwrite($socket, "HTTP/1.0 200 OK\r\nContent-Length: " . strlen($answer)
    . "\r\nConnection: close\r\n\r\n" . $answer);
fclose($socket);
fclose($server);
echo json_encode(array('headers' => $headers, 'body' => base64_encode($body)));
PHP;
    $process = proc_open(array(PHP_BINARY, '-c', __DIR__ . '/../php-test.ini', '-r', $peer, $response),
        array(0 => array('file', '/dev/null', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
    bodyBytesAssert(is_resource($process), 'The loopback peer starts');
    try {
        stream_set_timeout($pipes[1], 8);
        $address = trim((string)fgets($pipes[1]));
        bodyBytesAssert(preg_match('/^127\.0\.0\.1:[0-9]+$/D', $address) === 1, 'The peer announces its listener');
        $client = new Snoopy();
        $client->proxy_host = $client->proxy_port = '';
        $client->proxy_proto = 'http';
        $client->IP = null;
        $client->read_timeout = 3;
        $url = 'http://' . $address . '/body';
        if ($curl) {
            // _httpsrequest is the curl transport; HTTP keeps the peer local
            // without introducing a certificate fixture unrelated to body bytes.
            $client->host = '127.0.0.1';
            $client->port = parse_url($url, PHP_URL_PORT);
            $ok = $client->_httpsrequest($url, 'text/plain', $body, 'POST');
        } else {
            $ok = $client->fetch($url, 'POST', 'text/plain', $body);
        }
        $capture = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        unset($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        unset($pipes[2]);
        $code = proc_close($process);
        $process = null;
        bodyBytesAssert($code === 0 && $errors === '', 'The peer completes without errors: ' . $errors);
        bodyBytesAssert($ok && (int)$client->status === 200, 'The request succeeds: ' . $client->error);
        $request = json_decode($capture, true);
        bodyBytesAssert(is_array($request) && isset($request['headers'], $request['body']), 'The peer captured a request');
        $request['body'] = base64_decode($request['body'], true);
        return array($client, $request);
    } finally {
        foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
        if (is_resource($process)) {
            proc_terminate($process);
            proc_close($process);
        }
    }
}

$tests = array(
    'a literal zero response is not EOF' => function () {
        list($client) = bodyBytesRequest(false, '', '0');
        bodyBytesAssert($client->results === '0', 'The one-byte HTTP response must be retained');
    },
    'the socket request sends zero with its content length' => function () {
        list($client, $request) = bodyBytesRequest(false, '0');
        bodyBytesAssert($request['body'] === '0', 'The receiver must read the socket POST body');
        bodyBytesAssert(preg_match('/^Content-Length: 1\r?$/mi', $request['headers']) === 1, 'Zero needs content length 1');
    },
    'the curl request sends zero with its content length' => function () {
        list($client, $request) = bodyBytesRequest(true, '0');
        bodyBytesAssert($request['body'] === '0', 'The receiver must read the curl POST body');
        bodyBytesAssert(preg_match('/^Content-Length: 1\r?$/mi', $request['headers']) === 1, 'Zero needs content length 1');
    },
    'curl sends a leading at sign literally' => function () {
        $file = tempnam(sys_get_temp_dir(), 'snoopy-body-');
        try {
            $body = '@' . $file;
            // The old -d path must complete with wrong bytes, not merely time
            // out because its file length differs from the declared length.
            $canary = str_repeat('s', strlen($body));
            bodyBytesAssert(file_put_contents($file, $canary) === strlen($canary), 'The local canary is written');
            list($client, $request) = bodyBytesRequest(true, $body);
            bodyBytesAssert($request['body'] === $body, 'The POST must contain the supplied string, not file contents');
            bodyBytesAssert(file_get_contents($file) === $canary, 'The local canary remains unchanged');
        } finally {
            unlink($file);
        }
    },
    'empty and ordinary bodies keep their bytes on both transports' => function () {
        foreach (array(false, true) as $curl) {
            foreach (array('', 'plain=body&value=0') as $body) {
                list($client, $request) = bodyBytesRequest($curl, $body);
                bodyBytesAssert($request['body'] === $body, 'Ordinary and empty bodies must be preserved');
            }
        }
    },
);

$failures = 0;
foreach ($tests as $name => $test) {
    try {
        $test();
        echo 'ok - ' . $name . "\n";
    } catch (Throwable $error) {
        ++$failures;
        echo 'not ok - ' . $name . ': ' . $error->getMessage() . "\n";
    }
}
echo count($tests) . ' tests, ' . $failures . " failures\n";
exit($failures ? 1 : 0);
