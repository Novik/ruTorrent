<?php

// The socket writer/parser runs over duplex streams, as in SnoopyTest. The
// curl stand-in writes the same multi-block dump that curl -D produces. These
// are synthetic HTTP fixtures; no tracker, DNS lookup or remote peer is used.
require_once(__DIR__ . '/../../php/Snoopy.class.inc');
$GLOBALS['httpBlockPrivateNetworks'] = false;
$finalRoot = sys_get_temp_dir() . '/snoopy-final-' . getmypid();
mkdir($finalRoot, 0700);
$previousCurl = $pathToExternals['curl'] ?? null;
$previousFixture = getenv('SNOOPY_FINAL_ROOT');
putenv('SNOOPY_FINAL_ROOT=' . $finalRoot);
file_put_contents($finalRoot . '/curl.php', <<<'PHP'
<?php
$root = getenv('SNOOPY_FINAL_ROOT');
$request = array('url' => end($argv), 'method' => 'GET', 'cookie' => '');
for ($i = 1; $i < $argc - 1; $i++) {
    if ($argv[$i] === '-D') $header = $argv[++$i];
    elseif ($argv[$i] === '-o') $body = $argv[++$i];
    elseif ($argv[$i] === '-X') $request['method'] = $argv[++$i];
    elseif ($argv[$i] === '-H' && stripos($argv[$i + 1], 'Cookie:') === 0) $request['cookie'] = $argv[++$i];
}
$requests = unserialize(file_get_contents($root . '/requests'), array('allowed_classes' => false));
$requests[] = $request;
file_put_contents($root . '/requests', serialize($requests));
$plan = unserialize(file_get_contents($root . '/plan'), array('allowed_classes' => false));
$reply = array_shift($plan);
file_put_contents($root . '/plan', serialize($plan));
if (!is_array($reply)) exit(1);
file_put_contents($header, $reply[0]);
file_put_contents($body, $reply[1]);
exit($reply[2] ?? 0);
PHP
);
file_put_contents($finalRoot . '/curl', "#!/bin/sh\nexec " . escapeshellarg(PHP_BINARY)
    . ' -c ' . escapeshellarg(__DIR__ . '/../php-test.ini') . ' ' . escapeshellarg($finalRoot . '/curl.php') . ' "$@"' . "\n");
chmod($finalRoot . '/curl', 0700);
$pathToExternals['curl'] = $finalRoot . '/curl';

function finalSame($expected, $actual, $message)
{
    if ($expected !== $actual) throw new RuntimeException($message . '; expected ' . var_export($expected, true)
        . ', got ' . var_export($actual, true));
}

class FinalResponseSocket extends Snoopy
{
    public $replies;
    private $peers = array();
    public $requests = array();
    // A peer that stays open after its reply, as one still streaming would.
    public $keepOpen = false;

    public function connect()
    {
        $this->status = 0;
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        if ($pair === false) throw new RuntimeException('Could not create the private HTTP peer');
        $reply = array_shift($this->replies);
        if (!is_array($reply)) throw new RuntimeException('Unexpected offline socket request');
        fwrite($pair[1], $reply[0] . $reply[1]);
        if (!$this->keepOpen) stream_socket_shutdown($pair[1], STREAM_SHUT_WR);
        $this->peers[] = $pair[1];
        return $pair[0];
    }

    public function closeSockets()
    {
        foreach ($this->peers as $peer) {
            stream_set_blocking($peer, false);
            $wire = stream_get_contents($peer);
            preg_match('/^(\S+) (\S+)/', $wire, $request);
            preg_match('/^Cookie: (.*)\r?$/m', $wire, $cookie);
            $this->requests[] = array('method' => $request[1], 'url' => $request[2], 'cookie' => trim($cookie[0] ?? ''));
            fclose($peer);
        }
        $this->peers = array();
    }
}

function finalClient($transport, $replies)
{
    global $finalRoot, $finalClients;
    if ($transport === 'socket') {
        $client = new FinalResponseSocket();
        $client->replies = $replies;
        $finalClients[] = $client;
    } else {
        file_put_contents($finalRoot . '/plan', serialize($replies));
        file_put_contents($finalRoot . '/requests', serialize(array()));
        $client = new Snoopy();
    }
    $client->block_private = false;
    $client->proxy_host = $client->proxy_port = '';
    return $client;
}

function finalRequests($client)
{
    global $finalRoot;
    if ($client instanceof FinalResponseSocket) {
        $client->closeSockets();
        return $client->requests;
    }
    return unserialize(file_get_contents($finalRoot . '/requests'), array('allowed_classes' => false));
}

$tests = array();
foreach (array('socket' => 'http', 'curl' => 'https') as $transport => $scheme) {
    $url = $scheme . '://origin.test/start';
    $tests[$transport . ' exposes only final headers status and body after 100 and 103'] = function () use ($transport, $url) {
        $interim = "HTTP/1.1 100 Continue\r\nSet-Cookie: early=wrong\r\nLocation: /wrong\r\nContent-Encoding: gzip\r\n\r\n"
            . "HTTP/1.1 103 Early Hints\r\nSet-Cookie: hints=wrong\r\nLast-Modified: Thu, 01 Jan 1970 00:00:00 GMT\r\n\r\n";
        $final = "HTTP/1.1 200 OK\r\nSet-Cookie: final=right\r\nETag: final\r\nContent-Disposition: attachment; filename=\"final.torrent\"\r\n\r\n";
        $client = finalClient($transport, array(array($interim . $final, 'body')));
        $client->maxredirs = 0;
        finalSame(true, $client->fetch($url), 'The final plaintext body completes despite interim gzip');
        finalSame('200', $client->status, 'Final HTTP status');
        finalSame('body', $client->results, 'Interim/final headers never become body bytes');
        finalSame("HTTP/1.1 200 OK\r\n", $client->response_code, 'The status line belongs to the origin response');
        finalSame('final', trim($client->get_header('ETag')), 'Final header is readable');
        finalSame(false, $client->get_header('Last-Modified'), 'An interim header is absent');
        finalSame('final.torrent', $client->get_filename(), 'Filename consumer reads the final response');
        finalSame(false, $client->_redirectaddr, 'An interim Location cannot survive');
        $client->setcookies();
        finalSame(array('final' => 'right'), $client->cookies, 'Flat legacy cookies take only the final block');
    };
    $tests[$transport . ' follows only the final redirect and forwards only its cookie'] = function () use ($transport, $scheme, $url) {
        $target = $scheme . '://origin.test/next';
        $client = finalClient($transport, array(
            array("HTTP/1.1 103 Early Hints\r\nLocation: /wrong\r\nSet-Cookie: early=wrong\r\n\r\n"
                . "HTTP/1.1 302 Found\r\nLocation: " . $target . "\r\nSet-Cookie: final=right\r\n\r\n", ''),
            array("HTTP/1.1 200 OK\r\n\r\n", 'landing'),
        ));
        finalSame(true, $client->fetch($url), 'The real final redirect completes');
        finalSame('landing', $client->results, 'The redirected response owns the body');
        $requests = finalRequests($client);
        finalSame(2, count($requests), 'Only the source and final redirect are requested');
        finalSame($transport === 'socket' ? '/next' : $target, $requests[1]['url'], 'Only the final Location is followed');
        finalSame('Cookie: final=right', $requests[1]['cookie'], 'No interim cookie reaches the next request');
        finalSame(0, $client->_redirectdepth, 'The existing request-state contract still unwinds');
    };
    $tests[$transport . ' refuses an interim-only response without publishing its metadata'] = function () use ($transport, $url) {
        $client = finalClient($transport, array(array("HTTP/1.1 103 Early Hints\r\nSet-Cookie: early=wrong\r\n\r\n", '')));
        finalSame(false, $client->fetch($url), 'An interim answer is not a completed response');
        finalSame(Snoopy::RESPONSE_BODY_FAILED, $client->status, 'A missing final response has refusal status');
        finalSame('missing-final-response', $client->error, 'The refusal has a fixed reason');
        finalSame(array(), $client->headers, 'Interim headers are discarded');
        finalSame('', $client->results, 'No incomplete body is published');
        $client->setcookies();
        finalSame(array(), $client->cookies, 'Explicit setcookies cannot revive interim metadata');
    };
    $tests[$transport . ' keeps 101 Switching Protocols as the final response'] = function () use ($transport, $url) {
        $client = finalClient($transport, array(array("HTTP/1.1 101 Switching Protocols\r\nUpgrade: test\r\n\r\n", 'switched')));
        $client->maxredirs = 0;
        finalSame(true, $client->fetch($url), 'A 101 answer is final, not a provisional block');
        finalSame('101', $client->status, 'The 101 status is published');
        finalSame('switched', $client->results, 'The bytes after the 101 block are its body');
        finalSame('test', trim($client->get_header('Upgrade')), 'The 101 headers are published');
    };
    $tests[$transport . ' retains ordinary final gzip non-success and literal-zero responses'] = function () use ($transport, $url) {
        $body = 'decoded final body';
        $client = finalClient($transport, array(array("HTTP/1.1 404 Not Found\r\n0\r\nContent-Encoding: gzip\r\n\r\n", gzencode($body))));
        finalSame(true, $client->fetch($url), 'A non-success HTTP answer is still fetched');
        finalSame('404', $client->status, 'The final non-success status is preserved');
        finalSame($body, $client->results, 'Final gzip is still decoded');
        finalSame(true, in_array("0\r\n", $client->headers, true), 'A literal zero header is kept');
    };
}
// The cap bounds the socket reader. The curl path hands over curl's dump, and
// curl stops a stream of 1xx blocks by its own header limit or by -m.
$tests['socket accepts the most interim responses one answer may carry'] = function () {
    $interim = str_repeat("HTTP/1.1 103 Early Hints\r\nLink: </a.css>; rel=preload\r\n\r\n", Snoopy::MAX_INTERIM_RESPONSES);
    $client = finalClient('socket', array(array($interim . "HTTP/1.1 200 OK\r\n\r\n", 'body')));
    finalSame(true, $client->fetch('http://origin.test/start'), 'A final response after the allowed interim blocks completes');
    finalSame('body', $client->results, 'The final body is delivered');
};
$tests['socket refuses an interim response past the cap without reading on'] = function () {
    // The peer stays open and sends nothing more, so a reader that kept going
    // past the cap would wait for the read timeout and report -100 instead.
    $interim = str_repeat("HTTP/1.1 100 Continue\r\n\r\n", Snoopy::MAX_INTERIM_RESPONSES + 1);
    $client = finalClient('socket', array(array($interim, '')));
    $client->keepOpen = true;
    $client->read_timeout = 1;
    finalSame(false, $client->fetch('http://origin.test/start'), 'Interim blocks past the cap are refused, not read on');
    finalSame(Snoopy::RESPONSE_BODY_FAILED, $client->status, 'The refusal has refusal status, not a read timeout');
    finalSame('too-many-interim-responses', $client->error, 'The refusal has a fixed reason');
    finalSame(array(), $client->headers, 'No headers are published');
    finalSame('', $client->results, 'No body is published');
};
$tests['curl removes proxy CONNECT metadata before the final HTTP2 origin response'] = function () {
    $client = finalClient('curl', array(array("HTTP/1.1 200 Arbitrary tunnel phrase\r\nSet-Cookie: proxy=wrong\r\nLocation: /proxy\r\nContent-Encoding: gzip\r\n\r\n"
        . "HTTP/2 200\r\nETag: final\r\n\r\n", 'origin')));
    $client->maxredirs = 0;
    finalSame(true, $client->fetch('https://origin.test/start'), 'The final origin response completes');
    finalSame('200', $client->status, 'HTTP2 origin status');
    finalSame('origin', $client->results, 'CONNECT gzip does not decode the origin body');
    finalSame(false, $client->get_header('Set-Cookie'), 'Proxy cookies are absent');
    finalSame(false, $client->_redirectaddr, 'Proxy Location is absent');
};
$tests['curl CONNECT followed by TLS failure never publishes an origin response'] = function () {
    $client = finalClient('curl', array(array("HTTP/1.1 200 Arbitrary tunnel phrase\r\nSet-Cookie: proxy=wrong\r\n\r\n", 'partial', 28)));
    finalSame(false, $client->fetch('https://origin.test/start'), 'The TLS failure remains a curl error');
    finalSame(28, $client->status, 'curl exit code is preserved');
    finalSame(array(), $client->headers, 'CONNECT headers are not an origin response');
    finalSame('', $client->results, 'No failed-transfer body is exposed');
};

$failures = 0;
try {
    foreach ($tests as $name => $test) {
        $finalClients = array();
        try {
            $test();
            echo 'ok - ', $name, "\n";
        } catch (Throwable $error) {
            $failures++;
            echo 'not ok - ', $name, ': ', $error->getMessage(), "\n";
        } finally {
            foreach ($finalClients as $client) $client->closeSockets();
        }
    }
} finally {
    if ($previousCurl === null) unset($pathToExternals['curl']);
    else $pathToExternals['curl'] = $previousCurl;
    if ($previousFixture === false) putenv('SNOOPY_FINAL_ROOT');
    else putenv('SNOOPY_FINAL_ROOT=' . $previousFixture);
    foreach (glob($finalRoot . '/*') as $path) unlink($path);
    rmdir($finalRoot);
}
echo count($tests), ' tests, ', $failures, " failures\n";
exit($failures ? 1 : 0);
