<?php

/**
 * KATEngine::fetch() read Set-Cookie out of a response by hand, with the same
 * two captures Snoopy::setcookies() carried before they were bounded. What it
 * stores is written into the next request's Cookie header by hand, so the
 * subject here is the bytes of that request and not the jar.
 *
 * commonEngine is stubbed down to makeClient(): the real one evals the plugin
 * configuration, and the only part of it this path uses is the Snoopy it
 * returns -- which is the real Snoopy, because the writer under test is its.
 */

require_once(__DIR__ . '/../../../php/Snoopy.class.inc');

class KATBytesClient extends Snoopy
{
    public function connect()
    {
        $this->_pinnedaddr = '127.0.0.1';
        return parent::connect();
    }
}

class commonEngine
{
    public $defaults = array('public' => true, 'page_size' => 100);
    public $categories = array('All' => '');

    public function makeClient($url)
    {
        $client = new KATBytesClient();
        $client->proxy_host = $client->proxy_port = '';
        $client->proxy_proto = 'http';
        $client->block_private = false;
        $client->read_timeout = 2;
        $client->_fp_timeout = 3;
        $client->use_gzip = false;
        return $client;
    }

    public function getNewEntry()
    {
        return array('time' => 0, 'cat' => '', 'size' => 0, 'name' => '',
            'link' => '', 'desc' => '', 'seeds' => 0, 'peers' => 0);
    }

    public static function removeTags($value) { return strip_tags($value); }
    public static function formatSize($value) { return 0; }
}

require_once(__DIR__ . '/../../../plugins/extsearch/engines/KAT.php');

function katAssert($condition, $message)
{
    if (!$condition) throw new RuntimeException($message);
}

// Every field line of the header block, the request line excluded.
function katFieldLines($wire)
{
    $block = explode("\r\n\r\n", $wire, 2);
    $lines = explode("\r\n", $block[0]);
    array_shift($lines);
    return $lines;
}

// A peer that keeps every byte of every request, including whatever a client
// writes after a blank line: a reader that stops at the end of the header
// block cannot say whether the block ended where it should have.
function katPeer(array $responses, callable $body)
{
    $worker = <<<'PHP'
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if (!$server) { fwrite(STDERR, $error); exit(1); }
$address = stream_socket_get_name($server, false);
echo $address, "\n"; flush();
$responses = json_decode(base64_decode($argv[1]), true);
$requests = array();
while (true) {
    $read = array($server, STDIN); $write = $except = array();
    if (stream_select($read, $write, $except, 5) !== 1) break;
    if (in_array(STDIN, $read, true)) break;
    $peer = stream_socket_accept($server, 0);
    if (!$peer) exit(3);
    stream_set_blocking($peer, false);
    $request = ''; $idle = 0; $deadline = microtime(true) + 2;
    while (microtime(true) < $deadline) {
        $chunk = fread($peer, 65536);
        if ($chunk !== false && $chunk !== '') { $request .= $chunk; $idle = 0; continue; }
        if ($request !== '' && ++$idle > 8) break;
        usleep(10000);
    }
    $requests[] = base64_encode($request);
    stream_set_blocking($peer, true);
    $response = $responses ? array_shift($responses) : base64_encode("HTTP/1.0 200 OK\r\nContent-Length: 2\r\n\r\nok");
    @fwrite($peer, base64_decode($response));
    fclose($peer);
}
fclose($server);
echo json_encode($requests), "\n";
PHP;
    $process = proc_open(array(PHP_BINARY, '-c', __DIR__ . '/../../php-test.ini', '-r', $worker,
        base64_encode(json_encode($responses))),
        array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
    katAssert(is_resource($process), 'The local HTTP peer starts');
    try {
        stream_set_timeout($pipes[1], 8);
        $address = fgets($pipes[1]);
        katAssert(is_string($address) && preg_match('/^127\.0\.0\.1:([0-9]+)\s*$/D', $address, $match) === 1,
            'The peer announces its local port');
        $check = $body((int) $match[1]);
        fwrite($pipes[0], "done\n");
        $json = '';
        while (($line = fgets($pipes[1])) !== false) $json .= $line;
        $requests = json_decode(trim($json), true);
        katAssert(is_array($requests), 'The peer reports the requests it received');
        $check(array_map('base64_decode', $requests));
    } finally {
        foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
        proc_close($process);
    }
}

function katSearch($setCookieLines, callable $check)
{
    $page = '<div class="torrents_table__torrent_name"></div>';
    $first = base64_encode("HTTP/1.0 200 OK\r\n" . $setCookieLines . "Content-Length: 2\r\n\r\nok");
    $second = base64_encode("HTTP/1.0 200 OK\r\nContent-Length: " . strlen($page) . "\r\n\r\n" . $page);
    katPeer(array($first, $second), function ($port) use ($check) {
        $engine = new KATEngine();
        $engine->url = 'http://katcr.test:' . $port;
        $results = array();
        $engine->action('term', 'all', $results, 10, false);
        return $check;
    });
}

$tests = array(
    // A Set-Cookie carrying no attribute ends its value at the end of the
    // line, and fgets() keeps that line's CRLF in $client->headers. Those two
    // bytes are not part of the value, and the request that sends them ends
    // its own header block one line early.
    'an attributeless Set-Cookie leaves the search request intact' => function () {
        katSearch("Set-Cookie: sess=abc\r\n", function ($requests) {
            katAssert(count($requests) === 2, 'The search is the second request');
            $wire = $requests[1];
            katAssert(preg_match('/\r(?!\n)/', $wire) !== 1 && preg_match('/(?<!\r)\n/', $wire) !== 1,
                'Every CR and LF of the search request is part of a CRLF pair');
            katAssert(strpos($wire, "\r\n\r\n") + 4 === strlen($wire),
                'The first blank line of the request is the end of the request');
            katAssert(in_array('Cookie: sess=abc', katFieldLines($wire), true),
                'The cookie is one field line of the header block');
        });
    },
    // Two attributeless cookies: the "; " the writer puts between crumbs
    // follows a value that has already ended the line, so it starts a field
    // line of its own.
    'two attributeless cookies ride one Cookie line' => function () {
        katSearch("Set-Cookie: a=x\r\nSet-Cookie: b=y\r\n", function ($requests) {
            $wire = $requests[1];
            katAssert(in_array('Cookie: a=x; b=y', katFieldLines($wire), true),
                'Both crumbs ride one Cookie line');
            foreach (katFieldLines($wire) as $line)
                katAssert(strpos($line, ';') !== 0, 'No field line begins with a crumb separator');
        });
    },
    // fgets() splits the header stream on LF, so a bare CR is what a value can
    // still carry into the middle of a line.
    // A value whose control character is kept names a cookie, so the search
    // still goes out -- with the offending bytes in its Cookie header. A
    // bounded capture may instead name no cookie at all, and then the engine
    // makes no search request. Either way, no request may carry the bytes.
    'a control character inside the value never reaches the wire' => function () {
        katSearch("Set-Cookie: a=x\rX-Injected: 1\r\n", function ($requests) {
            foreach ($requests as $wire) {
                katAssert(preg_match('/\r(?!\n)/', $wire) !== 1,
                    'Every CR of every request is part of a CRLF pair');
                katAssert(strpos($wire, 'X-Injected') === false,
                    'Nothing the value carried after its control character reaches the wire');
            }
        });
    },
    'a control character inside the name never reaches the wire' => function () {
        katSearch("Set-Cookie: a\rX-Injected: 1=v\r\n", function ($requests) {
            foreach ($requests as $wire) {
                katAssert(preg_match('/\r(?!\n)/', $wire) !== 1,
                    'Every CR of every request is part of a CRLF pair');
                katAssert(strpos($wire, 'X-Injected') === false,
                    'Nothing the name carried after its control character reaches the wire');
            }
        });
    },
    // The engine reads the jar the writer reads, so the deletion the writer
    // honours is honoured here too.
    'a cookie with attributes still reaches the search request' => function () {
        katSearch("Set-Cookie: sess=abc; path=/; HttpOnly\r\n", function ($requests) {
            katAssert(in_array('Cookie: sess=abc', katFieldLines($requests[1]), true),
                'A cookie that ends at an attribute is sent as the attribute left it');
        });
    },
);

$failures = 0;
foreach ($tests as $name => $test) {
    try { $test(); echo 'ok - ', $name, "\n"; }
    catch (Throwable $error) { ++$failures; echo 'not ok - ', $name, ': ', $error->getMessage(), "\n"; }
}
echo count($tests), ' tests, ', $failures, " failures\n";
exit($failures ? 1 : 0);
