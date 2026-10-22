<?php

/**
 * TorrentechAccount::login() reads a session id out of the login form in a
 * response body and splices it straight into the next request's query string.
 * Snoopy writes the request line by hand, so the subject is that line: a
 * request target carries no whitespace and no control character, and a request
 * line is three space-separated parts.
 *
 * This is the shape #3432 found in the redirect target, reached from a body
 * instead of a header. parse_url() turns a control character in the target
 * into "_" and keeps what followed it, so a space in the captured id is enough
 * on its own -- no control character is needed to write a request line no
 * server will accept.
 *
 * Every address is 127.0.0.1, so no name is resolved and nothing leaves the
 * machine. The credentials are fake.
 */

require_once(__DIR__ . '/../../../plugins/loginmgr/accounts.php');
require_once(__DIR__ . '/../../../plugins/loginmgr/accounts/Torrentech.php');

define('TT_USER', 'fakeuser');
define('TT_PASS', 'fakepass');

function ttAssert($condition, $message)
{
    if (!$condition) throw new RuntimeException($message);
}

function ttRequestLine($wire)
{
    $end = strpos($wire, "\r\n");
    return $end === false ? $wire : substr($wire, 0, $end);
}

function ttPeer(array $responses, callable $body)
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
    @fwrite($peer, str_replace('{PORT}', $port, base64_decode($response)));
    fclose($peer);
}
fclose($server);
echo json_encode($requests), "\n";
PHP;
    $process = proc_open(array(PHP_BINARY, '-c', __DIR__ . '/../../php-test.ini', '-r', $worker,
        base64_encode(json_encode($responses))),
        array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
    ttAssert(is_resource($process), 'The local HTTP peer starts');
    try {
        stream_set_timeout($pipes[1], 8);
        $address = fgets($pipes[1]);
        ttAssert(is_string($address) && preg_match('/^127\.0\.0\.1:([0-9]+)\s*$/D', $address, $match) === 1,
            'The peer announces its local port');
        $check = $body((int) $match[1]);
        fwrite($pipes[0], "done\n");
        $json = '';
        while (($line = fgets($pipes[1])) !== false) $json .= $line;
        $requests = json_decode(trim($json), true);
        ttAssert(is_array($requests), 'The peer reports the requests it received');
        $check(array_map('base64_decode', $requests));
    } finally {
        foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
        proc_close($process);
    }
}

// The front page carries the login form. No Content-Length: the peer rewrites
// {PORT} into the body, which would make a declared length wrong, and an
// HTTP/1.0 body ends when the connection closes.
function ttLogin($sessionId, callable $check)
{
    $form = '<form action="http://127.0.0.1:{PORT}/index.php?s=' . $sessionId
        . '&amp;act=Login&amp;CODE=01&amp;CookieDate=1" method="post">';
    $front = base64_encode("HTTP/1.0 200 OK\r\nContent-Type: text/html\r\n\r\n" . $form);
    ttPeer(array($front), function ($port) use ($check) {
        $account = new TorrentechAccount();
        $account->url = 'http://127.0.0.1:' . $port;
        $client = new Snoopy();
        $client->proxy_host = $client->proxy_port = '';
        $client->proxy_proto = 'http';
        $client->block_private = false;
        $client->read_timeout = 2;
        $client->_fp_timeout = 3;
        $client->use_gzip = false;
        $method = new ReflectionMethod('TorrentechAccount', 'login');
        if (PHP_VERSION_ID < 80100) $method->setAccessible(true);
        $url = $verb = $type = $body = $fetched = null;
        $method->invokeArgs($account, array($client, TT_USER, TT_PASS,
            &$url, &$verb, &$type, &$body, &$fetched));
        return $check;
    });
}

// No request this account makes may carry a request line a server refuses, and
// none may carry the credentials to a target the form did not name.
function ttEveryRequestWellFormed($requests)
{
    foreach ($requests as $index => $wire) {
        $line = ttRequestLine($wire);
        ttAssert(count(explode(' ', $line)) === 3,
            "Request $index has a request line of three parts: " . var_export($line, true));
        ttAssert(preg_match('/[[:cntrl:]]/', $line) !== 1,
            "Request $index has no control character in its request line");
    }
}

$tests = array(
    // The control: a session id of the shape the forum really issues is
    // spliced in as it stands, and the credentials are posted to it.
    'a clean session id is posted to exactly the named target' => function () {
        ttLogin('3f6a1c0e9b', function ($requests) {
            ttAssert(count($requests) === 2, 'The credential POST is made');
            ttEveryRequestWellFormed($requests);
            ttAssert(ttRequestLine($requests[1])
                === 'POST /index.php?s=3f6a1c0e9b&act=Login&CODE=01&CookieDate=1 HTTP/1.0',
                'The session id reaches the target exactly as the form named it');
        });
    },
    // A space needs no control character to break the request line: it is
    // what a server's parser reads as the end of the target.
    'a space in the session id reaches no request line' => function () {
        ttLogin('3f6a HTTP/1.0', function ($requests) {
            ttEveryRequestWellFormed($requests);
            foreach ($requests as $wire)
                ttAssert(strpos($wire, 'UserName=') === false,
                    'No credentials are posted to a target the form did not name');
        });
    },
    // parse_url() replaces a control character in the target with "_" and
    // keeps what followed, so the bytes behind it reach the request line too.
    'a carriage return in the session id reaches no request line' => function () {
        ttLogin("3f6a\rX-Injected: yes", function ($requests) {
            ttEveryRequestWellFormed($requests);
            foreach ($requests as $wire)
                ttAssert(strpos($wire, 'X-Injected') === false,
                    'Nothing behind the carriage return reaches the wire');
        });
    },
    'a tab in the session id reaches no request line' => function () {
        ttLogin("3f6a\tmore", function ($requests) {
            ttEveryRequestWellFormed($requests);
            foreach ($requests as $wire)
                ttAssert(strpos($wire, 'UserName=') === false,
                    'No credentials are posted to a target the form did not name');
        });
    },
    // A control character that is not whitespace leaves the request line
    // well-formed: parse_url() rewrites it to "_" and keeps what followed.
    // The request is then one a server accepts, asking about a session the
    // form never named -- which is why the bound declines the capture
    // instead of truncating it.
    'a control character in the session id posts no credentials' => function () {
        ttLogin("3f6a\x01tail", function ($requests) {
            ttEveryRequestWellFormed($requests);
            foreach ($requests as $wire) {
                ttAssert(strpos($wire, 'UserName=') === false,
                    'No credentials are posted to a target the form did not name');
                ttAssert(strpos($wire, '3f6a_tail') === false,
                    'No request names the session the rewriting produced');
            }
        });
    },
    'a DEL byte in the session id posts no credentials' => function () {
        ttLogin("3f6a\x7ftail", function ($requests) {
            ttEveryRequestWellFormed($requests);
            foreach ($requests as $wire)
                ttAssert(strpos($wire, 'UserName=') === false,
                    'No credentials are posted to a target the form did not name');
        });
    },
    // A path written in UTF-8 is a target servers accept today, and the bound
    // is on whitespace and control characters, not on byte values.
    'a session id of bytes over 0x7f is still followed' => function () {
        ttLogin("3f6a\xc3\xa9", function ($requests) {
            ttAssert(count($requests) === 2, 'The credential POST is made');
            ttEveryRequestWellFormed($requests);
            ttAssert(strpos($requests[1], "s=3f6a\xc3\xa9&act=Login") !== false,
                'The high bytes reach the target as the form named them');
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
