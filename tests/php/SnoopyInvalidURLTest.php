<?php

require_once(__DIR__ . '/../../php/Snoopy.class.inc');

function invalidURLAssert($condition, $message)
{
    if (!$condition) throw new RuntimeException($message);
}

class SnoopyInvalidURLClient extends Snoopy
{
    public $connections = 0;

    function connect()
    {
        ++$this->connections;
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        invalidURLAssert(is_array($pair), 'The successful response fixture opens');
        fwrite($pair[1], "HTTP/1.0 200 OK\r\nContent-Length: 2\r\n\r\nok");
        stream_socket_shutdown($pair[1], STREAM_SHUT_WR);
        $this->peer = $pair[1];
        return $pair[0];
    }

    public $peer;
}

$tests = array(
    'the refusal statuses keep the numbers callers compare against' => function () {
        // A status below 100 means no reply arrived; the number says which
        // refusal it was, and callers read the number rather than the
        // constant -- plugins/rss/rss.php tests $cli->status for -100 to
        // tell a read timeout from anything else. Renumbering one of these
        // is a protocol change, so the values are asserted, not just the names.
        invalidURLAssert(Snoopy::RESPONSE_BODY_FAILED === -101, 'A refused request reports -101');
        invalidURLAssert(Snoopy::SOCKET_CONNECT_FAILED === -102, 'A failed connection reports -102');
    },
    'malformed URL encoding does not throw' => function () {
        foreach (array('http://example.test:99999/path', 'http://:') as $url) {
            invalidURLAssert(Snoopy::linkencode($url) === $url, 'Malformed input remains available for fetch refusal');
        }
        invalidURLAssert(Snoopy::linkencode('https://example.test/a b?q=c d') === 'https://example.test/a%20b?q=c+d',
            'Valid URLs retain their encoding');
    },
    'both fetch doors discard a prior success on an invalid URL' => function () {
        foreach (array('fetch', 'fetchComplex') as $method) {
            foreach (array('http://example.test:99999/path', 'http://:', '/relative', '//example.test/path') as $url) {
                $client = new SnoopyInvalidURLClient();
                $client->proxy_host = $client->proxy_port = '';
                $client->proxy_proto = 'http';
                // conf/config.php reaches this client through php/util.php,
                // and the guard refuses the fixture host before the seed
                // request is made.
                $client->block_private = false;
                try {
                    invalidURLAssert($client->fetch('http://fixture.test/ok'), 'A real parsed response seeds the client');
                    invalidURLAssert($client->results === 'ok' && (int)$client->status === 200, 'The seed response is successful');
                    $client->_redirectaddr = 'http://fixture.test/stale';
                    invalidURLAssert($client->$method($url) === false, 'Invalid input must be refused');
                    invalidURLAssert($client->status === Snoopy::RESPONSE_BODY_FAILED, 'Refusal must replace the old HTTP status');
                    invalidURLAssert($client->error === 'invalid-url', 'The refusal reason contains no URL data');
                    invalidURLAssert($client->results === '' && $client->headers === array() && $client->response_code === '',
                        'Refusal must discard the earlier response');
                    invalidURLAssert($client->_redirectaddr === false, 'Refusal must discard the earlier redirect');
                    invalidURLAssert($client->connections === 1, 'Invalid input never opens a connection');
                } finally {
                    if (is_resource($client->peer)) fclose($client->peer);
                }
            }
        }
    },
    'URL cookies work on host-only and explicit-port addresses' => function () {
        foreach (array('http://fixture.test', 'http://fixture.test:80', 'http://fixture.test/') as $url) {
            $client = new SnoopyInvalidURLClient();
            $client->proxy_host = $client->proxy_port = '';
            $client->proxy_proto = 'http';
            $client->block_private = false;
            try {
                invalidURLAssert($client->fetchComplex($url . ':COOKIE:session=abc'), 'URL cookies must be stripped before URL validation');
                invalidURLAssert($client->results === 'ok' && (int)$client->status === 200, 'The cookie request succeeds');
                $request = stream_get_contents($client->peer);
                invalidURLAssert(strpos($request, "Cookie: session=abc\r\n") !== false, 'The parsed cookie reaches the wire');
                invalidURLAssert(strpos($request, 'GET / HTTP/1.0') === 0, 'The suffix is not part of the request target');
            } finally {
                if (is_resource($client->peer)) fclose($client->peer);
            }
        }
    },
);

// Warnings from parse_url consumers are a failed refusal, including on PHP 7.4.
set_error_handler(function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) throw new ErrorException($message, 0, $severity, $file, $line);
    return false;
});
$failures = 0;
try {
    foreach ($tests as $name => $test) {
        try {
            $test();
            echo 'ok - ' . $name . "\n";
        } catch (Throwable $error) {
            ++$failures;
            echo 'not ok - ' . $name . ': ' . $error->getMessage() . "\n";
        }
    }
} finally {
    restore_error_handler();
}
echo count($tests) . ' tests, ' . $failures . " failures\n";
exit($failures ? 1 : 0);
