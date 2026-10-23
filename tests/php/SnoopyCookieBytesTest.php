<?php

require_once(__DIR__ . '/../../php/Snoopy.class.inc');

// Pin only test DNS to the local peer; the production connector and HTTP writer run.
class CookieBytesClient extends Snoopy
{
    public function connect()
    {
        $this->_pinnedaddr = '127.0.0.1';
        return parent::connect();
    }
}

function bytesAssert($condition, $message)
{
    if (!$condition) throw new RuntimeException($message);
}

// What the bytes of a request say about its structure, independent of which
// header carried the offending value. A well-formed request puts every CR and
// LF in a CRLF pair, ends its header block exactly once, and ends there.
function requestShape($wire)
{
    return array(
        'strayCR' => preg_match('/\r(?!\n)/', $wire) === 1,
        'strayLF' => preg_match('/(?<!\r)\n/', $wire) === 1,
        'blankLines' => substr_count($wire, "\r\n\r\n"),
        'endsAtBlankLine' => substr($wire, -4) === "\r\n\r\n",
        'nul' => strpos($wire, "\x00") !== false,
    );
}

// Every field line of the header block, the request line excluded.
function fieldLines($wire)
{
    $block = explode("\r\n\r\n", $wire, 2);
    $lines = explode("\r\n", $block[0]);
    array_shift($lines);
    return $lines;
}

// A peer that keeps every byte a request put on the wire, including whatever
// follows a blank line: the question is what the request was made of, and a
// reader that stops at the end of the header block cannot answer it.
function bytesPeer(array $responses, callable $body)
{
    $worker = <<<'PHP'
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if (!$server) { fwrite(STDERR, $error); exit(1); }
$address = stream_socket_get_name($server, false);
$port = substr(strrchr($address, ':'), 1);
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
    $request = '';
    $idle = 0;
    $deadline = microtime(true) + 2;
    while (microtime(true) < $deadline) {
        $chunk = fread($peer, 65536);
        if ($chunk !== false && $chunk !== '') { $request .= $chunk; $idle = 0; continue; }
        if ($request !== '' && ++$idle > 8) break;
        usleep(10000);
    }
    $requests[] = base64_encode($request);
    stream_set_blocking($peer, true);
    $response = $responses ? array_shift($responses) : array('wire' => base64_encode("HTTP/1.0 200 OK\r\nContent-Length: 2\r\n\r\nok"));
    @fwrite($peer, str_replace('{PORT}', $port, base64_decode($response['wire'])));
    fclose($peer);
}
fclose($server);
echo json_encode($requests), "\n";
PHP;
    $process = proc_open(array(PHP_BINARY, '-c', __DIR__ . '/../php-test.ini', '-r', $worker,
        base64_encode(json_encode($responses))),
        array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
    bytesAssert(is_resource($process), 'The local HTTP peer starts');
    try {
        stream_set_timeout($pipes[1], 8);
        $address = fgets($pipes[1]);
        bytesAssert(is_string($address) && preg_match('/^127\.0\.0\.1:([0-9]+)\s*$/D', $address, $match) === 1,
            'The peer announces its local port');
        $client = new CookieBytesClient();
        $client->proxy_host = $client->proxy_port = '';
        $client->proxy_proto = 'http';
        $client->block_private = false;
        $client->read_timeout = 2;
        $client->_fp_timeout = 3;
        $client->use_gzip = false;
        $check = $body($client, (int) $match[1]);
        fwrite($pipes[0], "done\n");
        $json = '';
        while (($line = fgets($pipes[1])) !== false) $json .= $line;
        $requests = json_decode(trim($json), true);
        bytesAssert(is_array($requests), 'The peer reports the requests it received');
        $check(array_map('base64_decode', $requests));
    } finally {
        foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
        proc_close($process);
    }
}

function redirectTo($setCookie, $eol = "\r\n")
{
    return array('wire' => base64_encode('HTTP/1.0 302 Found' . $eol . $setCookie . $eol
        . 'Location: http://first.test:{PORT}/next' . $eol . $eol));
}

$ok = array('wire' => base64_encode("HTTP/1.0 200 OK\r\nContent-Length: 2\r\n\r\nok"));

$tests = array(
    // A Set-Cookie carrying no attribute ends its value at the end of the
    // line, and the line ends with a CRLF. Those two bytes are not part of
    // the value, and a request that sends them ends its own header block.
    'a cookie from an attributeless Set-Cookie keeps the request intact' => function () use ($ok) {
        bytesPeer(array(redirectTo('Set-Cookie: stale=yes'), $ok), function ($client, $port) {
            $client->rawheaders['X-Caller'] = 'present';
            bytesAssert($client->fetch('http://first.test:' . $port . '/start') && $client->results === 'ok',
                'The redirect is followed to its answer');
            return function ($requests) use ($client) {
                bytesAssert(count($requests) === 2, 'The chain is two requests long');
                $shape = requestShape($requests[1]);
                bytesAssert(!$shape['strayCR'] && !$shape['strayLF'],
                    'Every CR and LF of the request is part of a CRLF pair');
                bytesAssert($shape['blankLines'] === 1 && $shape['endsAtBlankLine'],
                    'The request ends its header block once, at its end');
                bytesAssert(in_array('Cookie: stale=yes', fieldLines($requests[1]), true),
                    'The cookie is one field line of the header block');
                bytesAssert(in_array('X-Caller: present', fieldLines($requests[1]), true),
                    'A header behind the cookie stays inside the header block');
                bytesAssert($client->cookies === array('stale' => 'yes'),
                    'The jar holds the value and not the line it arrived on');
            };
        });
    },
    // Two attributeless cookies: the separator the writer puts between crumbs
    // follows the first value, so a value ending a line starts a field line
    // of its own with whatever came next.
    'two such cookies put no field line of their own on the wire' => function () use ($ok) {
        $two = array('wire' => base64_encode("HTTP/1.0 302 Found\r\nSet-Cookie: a=x\r\nSet-Cookie: b=y\r\n"
            . "Location: http://first.test:{PORT}/next\r\n\r\n"));
        bytesPeer(array($two, $ok), function ($client, $port) {
            bytesAssert($client->fetch('http://first.test:' . $port . '/start') && $client->results === 'ok',
                'The redirect is followed to its answer');
            return function ($requests) use ($client) {
                $shape = requestShape($requests[1]);
                bytesAssert(!$shape['strayCR'] && !$shape['strayLF'] && $shape['blankLines'] === 1,
                    'The request is one header block with no stray CR or LF');
                bytesAssert(in_array('Cookie: a=x; b=y', fieldLines($requests[1]), true),
                    'Both crumbs ride one Cookie line');
                foreach (fieldLines($requests[1]) as $line)
                    bytesAssert(strpos($line, ';') !== 0, 'No field line begins with a crumb separator');
                bytesAssert($client->cookies === array('a' => 'x', 'b' => 'y'),
                    'Both values are stored clean');
            };
        });
    },
    // A bare CR inside the value is a control character the value may not
    // carry either, whatever a given server does with it.
    'a control character inside the value never reaches the wire' => function () use ($ok) {
        bytesPeer(array(redirectTo("Set-Cookie: a=x\rX-Injected: 1"), $ok), function ($client, $port) {
            bytesAssert($client->fetch('http://first.test:' . $port . '/start') && $client->results === 'ok',
                'The redirect is followed to its answer');
            return function ($requests) {
                $shape = requestShape($requests[1]);
                bytesAssert(!$shape['strayCR'] && !$shape['strayLF'],
                    'Every CR and LF of the request is part of a CRLF pair');
                bytesAssert(strpos($requests[1], 'X-Injected') === false,
                    'Nothing the value carried after its control character reaches the wire');
            };
        });
    },
    // The name is written into the same header line as the value, and is
    // whatever precedes the first "=" of the line.
    'a control character inside the name never reaches the wire' => function () use ($ok) {
        bytesPeer(array(redirectTo("Set-Cookie: a\rX-Injected: 1=v"), $ok), function ($client, $port) {
            bytesAssert($client->fetch('http://first.test:' . $port . '/start') && $client->results === 'ok',
                'The redirect is followed to its answer');
            return function ($requests) {
                $shape = requestShape($requests[1]);
                bytesAssert(!$shape['strayCR'] && !$shape['strayLF'],
                    'Every CR and LF of the request is part of a CRLF pair');
                bytesAssert(strpos($requests[1], 'X-Injected') === false,
                    'Nothing the name carried after its control character reaches the wire');
            };
        });
    },
    // A Set-Cookie with no "=" before its first ";" names no cookie.
    'a Set-Cookie with no name before its first attribute sets nothing' => function () use ($ok) {
        bytesPeer(array(redirectTo('Set-Cookie: a; X-Injected: 1=v'), $ok), function ($client, $port) {
            bytesAssert($client->fetch('http://first.test:' . $port . '/start') && $client->results === 'ok',
                'The redirect is followed to its answer');
            return function ($requests) use ($client) {
                foreach (fieldLines($requests[1]) as $line)
                    bytesAssert(strpos($line, 'Cookie:') !== 0, 'The request carries no cookie');
                bytesAssert($client->cookies === array(), 'The jar stays empty');
            };
        });
    },
    'a NUL inside the value never reaches the wire' => function () use ($ok) {
        bytesPeer(array(redirectTo("Set-Cookie: a=x\x00y"), $ok), function ($client, $port) {
            bytesAssert($client->fetch('http://first.test:' . $port . '/start') && $client->results === 'ok',
                'The redirect is followed to its answer');
            return function ($requests) {
                bytesAssert(!requestShape($requests[1])['nul'], 'No NUL byte reaches the wire');
            };
        });
    },
    // A server that ends its header lines with a bare LF is answered the same
    // way: the terminator is not part of the value.
    'LF-only line endings leave the value and the request clean' => function () use ($ok) {
        bytesPeer(array(redirectTo('Set-Cookie: a=x', "\n"), $ok), function ($client, $port) {
            bytesAssert($client->fetch('http://first.test:' . $port . '/start') && $client->results === 'ok',
                'The redirect is followed to its answer');
            return function ($requests) use ($client) {
                $shape = requestShape($requests[1]);
                bytesAssert(!$shape['strayCR'] && !$shape['strayLF'] && $shape['blankLines'] === 1,
                    'The request is one header block with no stray CR or LF');
                bytesAssert($client->cookies === array('a' => 'x'),
                    'The jar holds the value without its terminator');
            };
        });
    },
    // The deletion the writer looks for is the value "deleted", which an
    // attributeless Set-Cookie never matched while the terminator was part of
    // the value it compared.
    'an attributeless deletion deletes the cookie' => function () use ($ok) {
        $set = array('wire' => base64_encode("HTTP/1.0 302 Found\r\nSet-Cookie: a=x; path=/\r\n"
            . "Location: http://first.test:{PORT}/next\r\n\r\n"));
        $clear = array('wire' => base64_encode("HTTP/1.0 302 Found\r\nSet-Cookie: a=deleted\r\n"
            . "Location: http://first.test:{PORT}/last\r\n\r\n"));
        bytesPeer(array($set, $clear, $ok), function ($client, $port) {
            bytesAssert($client->fetch('http://first.test:' . $port . '/start') && $client->results === 'ok',
                'The two redirects are followed to the answer');
            return function ($requests) use ($client) {
                bytesAssert(count($requests) === 3, 'The chain is three requests long');
                foreach (fieldLines($requests[2]) as $line)
                    bytesAssert(strpos($line, 'Cookie:') !== 0, 'The deleted cookie reaches no later request');
                bytesAssert($client->cookies === array(), 'The deleted cookie leaves the jar');
            };
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
