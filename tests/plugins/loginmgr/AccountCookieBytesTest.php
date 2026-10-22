<?php

/**
 * NNMClub and LostFilm read a cookie out of a response BODY and put it in the
 * jar Snoopy writes into the next request's Cookie header by hand. A body is
 * not a header: fgets() splits the header stream on LF, so a header capture
 * can only carry a bare CR, while a body capture carries CR and LF both -- and
 * a value carrying a blank line ends the header block of the request that
 * sends it, leaving everything behind that line, Authorization included,
 * outside the request.
 *
 * The subject is therefore the bytes of the request these accounts cause, not
 * the jar. The real plugins/loginmgr/accounts.php is loaded so the base class
 * is the shipped one, and the client is a real Snoopy: the writer under test
 * is its. Every address is 127.0.0.1, so no name is resolved and nothing
 * leaves the machine. The credentials are fake.
 */

require_once(__DIR__ . '/../../../plugins/loginmgr/accounts.php');
require_once(__DIR__ . '/../../../plugins/loginmgr/accounts/NNMClub.php');
require_once(__DIR__ . '/../../../plugins/loginmgr/accounts/LostFilm.php');

define('LM_USER', 'fakeuser');
define('LM_PASS', 'fakepass');
define('LM_AUTH', 'Authorization: Basic ' . base64_encode(LM_USER . ':' . LM_PASS));

function lmAssert($condition, $message)
{
    if (!$condition) throw new RuntimeException($message);
}

function lmHeaderBlock($wire)
{
    $pos = strpos($wire, "\r\n\r\n");
    return $pos === false ? $wire : substr($wire, 0, $pos);
}

// Every field line of the header block, the request line excluded.
function lmFieldLines($wire)
{
    $lines = explode("\r\n", lmHeaderBlock($wire));
    array_shift($lines);
    return $lines;
}

// What a well-formed request looks like byte for byte, whichever header
// carried the offending value: every CR and LF in a CRLF pair, and exactly one
// blank line -- the one that ends the header block.
function lmWellFormed($wire, $what)
{
    lmAssert(preg_match('/\r(?!\n)/', $wire) !== 1, "Every CR of $what is part of a CRLF pair");
    lmAssert(preg_match('/(?<!\r)\n/', $wire) !== 1, "Every LF of $what is part of a CRLF pair");
    lmAssert(substr_count($wire, "\r\n\r\n") === 1, "$what ends its header block exactly once");
}

function lmPeer(array $responses, callable $body)
{
    $worker = <<<'PHP'
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if (!$server) { fwrite(STDERR, $error); exit(1); }
echo stream_socket_get_name($server, false), "\n"; flush();
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
    lmAssert(is_resource($process), 'The local HTTP peer starts');
    try {
        stream_set_timeout($pipes[1], 8);
        $address = fgets($pipes[1]);
        lmAssert(is_string($address) && preg_match('/^127\.0\.0\.1:([0-9]+)\s*$/D', $address, $match) === 1,
            'The peer announces its local port');
        $check = $body((int) $match[1]);
        fwrite($pipes[0], "done\n");
        $json = '';
        while (($line = fgets($pipes[1])) !== false) $json .= $line;
        $requests = json_decode(trim($json), true);
        lmAssert(is_array($requests), 'The peer reports the requests it received');
        $check(array_map('base64_decode', $requests));
    } finally {
        foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
        proc_close($process);
    }
}

function lmClient()
{
    $client = new Snoopy();
    $client->proxy_host = $client->proxy_port = '';
    $client->proxy_proto = 'http';
    $client->block_private = false;
    $client->read_timeout = 2;
    $client->_fp_timeout = 3;
    $client->use_gzip = false;
    $client->user = LM_USER;
    $client->pass = LM_PASS;
    return $client;
}

function lmHtml($body)
{
    return base64_encode("HTTP/1.0 200 OK\r\nContent-Type: text/html\r\nContent-Length: "
        . strlen($body) . "\r\n\r\n" . $body);
}

// NNMClub::login(): the first response carries the script that names the
// _ddn_ cookie, the second answers the credential POST.
function nnmLogin($scriptValue, callable $check)
{
    $page = "<script>document.cookie='_ddn_" . $scriptValue . ";'</script>";
    lmPeer(array(lmHtml($page)), function ($port) use ($check) {
        $account = new NNMClubAccount();
        $account->url = 'http://127.0.0.1:' . $port;
        $client = lmClient();
        $method = new ReflectionMethod('NNMClubAccount', 'login');
        if (PHP_VERSION_ID < 80100) $method->setAccessible(true);
        $url = $verb = $type = $body = $fetched = null;
        $method->invokeArgs($account, array($client, LM_USER, LM_PASS,
            &$url, &$verb, &$type, &$body, &$fetched));
        return $check;
    });
}

// LostFilm::isOKPostFetch(): the first response is the details page that names
// the dlt token, the second answers the retried download.
function lostfilmRetry($tokenValue, callable $check)
{
    $download = '/download.php?id=42&x=1';
    $details = '<a href="' . $download . '" onMouseOver="setCookie(\'dlt\',\'' . $tokenValue . '\'';
    $answer = base64_encode("HTTP/1.0 200 OK\r\nContent-Disposition: attachment; filename=\"a.torrent\"\r\n"
        . "Content-Length: 2\r\n\r\nok");
    lmPeer(array(lmHtml($details), $answer), function ($port) use ($check, $download) {
        $account = new LostFilmAccount();
        $account->url = 'http://127.0.0.1:' . $port;
        $client = lmClient();
        $client->status = 200;
        $client->results = 'a page with no guest marker';
        $client->lastredirectaddr = 'http://127.0.0.1:' . $port . '/browse.php?cat=1';
        $method = new ReflectionMethod('LostFilmAccount', 'isOKPostFetch');
        if (PHP_VERSION_ID < 80100) $method->setAccessible(true);
        $method->invoke($account, $client, 'http://127.0.0.1:' . $port . $download, 'GET', '', '');
        return $check;
    });
}

$tests = array(
    // The control: a token the script could really name is sent as named, so
    // a bound that refused everything would not pass this file.
    'a clean _ddn_ token rides the credential POST' => function () {
        nnmLogin("tok=v1", function ($requests) {
            lmAssert(count($requests) === 2, 'The login is the second request');
            lmWellFormed($requests[1], 'the credential POST');
            lmAssert(in_array('Cookie: _ddn_tok=v1', lmFieldLines($requests[1]), true),
                'The token is one field line of the header block');
            lmAssert(in_array(LM_AUTH, lmFieldLines($requests[1]), true),
                'The credentials stay inside the header block');
        });
    },
    // A CRLF inside the value: the value ends the Cookie line, and what
    // follows it becomes a field line the server reads as a header of ours.
    'a line terminator in the _ddn_ value reaches no request' => function () {
        nnmLogin("tok=v1\r\nX-Injected: yes", function ($requests) {
            foreach ($requests as $index => $wire) {
                lmWellFormed($wire, "request $index");
                lmAssert(strpos($wire, 'X-Injected') === false,
                    "Nothing behind the terminator reaches request $index");
            }
            lmAssert(in_array(LM_AUTH, lmFieldLines($requests[count($requests) - 1]), true),
                'The credentials stay inside the header block');
        });
    },
    // A blank line inside the value ends the header block of the request that
    // sends it. Everything the writer puts after the Cookie line -- the
    // content headers and the Authorization header -- is then written outside
    // the request, where a conforming server reads it as a second one.
    'a blank line in the _ddn_ value strands no header outside the request' => function () {
        nnmLogin("tok=v1\r\n\r\nGET /smuggled HTTP/1.0\r\nHost: 127.0.0.1\r\n", function ($requests) {
            foreach ($requests as $index => $wire) {
                lmWellFormed($wire, "request $index");
                lmAssert(strpos($wire, '/smuggled') === false,
                    "No second request line reaches request $index");
            }
            lmAssert(in_array(LM_AUTH, lmFieldLines($requests[count($requests) - 1]), true),
                'The credentials stay inside the header block');
        });
    },
    // A body carries a lone LF as readily as a CRLF, which is the part of
    // this primitive a header capture does not have: fgets() would have
    // ended the header there.
    'a bare LF in the _ddn_ value reaches no request' => function () {
        nnmLogin("tok=v1\nX-Injected: yes", function ($requests) {
            foreach ($requests as $index => $wire) {
                lmWellFormed($wire, "request $index");
                lmAssert(strpos($wire, 'X-Injected') === false,
                    "Nothing behind the line feed reaches request $index");
            }
        });
    },
    'a bare CR in the _ddn_ value reaches no request' => function () {
        nnmLogin("tok=v1\rX-Injected: yes", function ($requests) {
            foreach ($requests as $index => $wire) {
                lmWellFormed($wire, "request $index");
                lmAssert(strpos($wire, 'X-Injected') === false,
                    "Nothing behind the carriage return reaches request $index");
            }
        });
    },
    'a line terminator in the _ddn_ name reaches no request' => function () {
        nnmLogin("tok\r\nX-Injected: yes=v1", function ($requests) {
            foreach ($requests as $index => $wire) {
                lmWellFormed($wire, "request $index");
                lmAssert(strpos($wire, 'X-Injected') === false,
                    "Nothing behind the terminator reaches request $index");
            }
        });
    },
    'a clean dlt token rides the retried download' => function () {
        lostfilmRetry('abc123', function ($requests) {
            lmAssert(count($requests) === 2, 'The download is retried');
            lmWellFormed($requests[1], 'the retried download');
            lmAssert(in_array('Cookie: dlt_2=abc123', lmFieldLines($requests[1]), true),
                'The token is one field line of the header block');
            lmAssert(in_array(LM_AUTH, lmFieldLines($requests[1]), true),
                'The credentials stay inside the header block');
        });
    },
    'a line terminator in the dlt token reaches no request' => function () {
        lostfilmRetry("abc\r\nX-Injected: yes", function ($requests) {
            foreach ($requests as $index => $wire) {
                lmWellFormed($wire, "request $index");
                lmAssert(strpos($wire, 'X-Injected') === false,
                    "Nothing behind the terminator reaches request $index");
            }
        });
    },
    'a bare LF in the dlt token reaches no request' => function () {
        lostfilmRetry("abc\nX-Injected: yes", function ($requests) {
            foreach ($requests as $index => $wire) {
                lmWellFormed($wire, "request $index");
                lmAssert(strpos($wire, 'X-Injected') === false,
                    "Nothing behind the line feed reaches request $index");
            }
        });
    },
    'a bare CR in the dlt token reaches no request' => function () {
        lostfilmRetry("abc\rX-Injected: yes", function ($requests) {
            foreach ($requests as $index => $wire) {
                lmWellFormed($wire, "request $index");
                lmAssert(strpos($wire, 'X-Injected') === false,
                    "Nothing behind the carriage return reaches request $index");
            }
        });
    },
    'a blank line in the dlt token strands no header outside the request' => function () {
        lostfilmRetry("abc\r\n\r\nGET /smuggled HTTP/1.0\r\nHost: 127.0.0.1\r\n", function ($requests) {
            foreach ($requests as $index => $wire) {
                lmWellFormed($wire, "request $index");
                lmAssert(strpos($wire, '/smuggled') === false,
                    "No second request line reaches request $index");
            }
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
