<?php

require_once(__DIR__ . '/../../php/Snoopy.class.inc');

function redirectTargetAssert($condition, $message)
{
    if (!$condition) throw new RuntimeException($message);
}

// What the bytes of a request line say about its structure. A request line is
// a method, a target and a version with one space between each, so it holds
// exactly two spaces and its target holds none; a target carrying whitespace
// or a control character makes a line of some other shape, whatever a given
// server then does with it.
function requestLineShape($wire)
{
    $line = explode("\r\n", $wire, 2);
    $line = $line[0];
    return array(
        'line' => $line,
        'spaces' => substr_count($line, ' '),
        'target' => preg_match('|^([A-Z]+) (.*) (HTTP/[0-9.]+)$|D', $line, $match) === 1 ? $match[2] : null,
    );
}

// Every field line of the header block, the request line excluded.
function redirectFieldLines($wire)
{
    $block = explode("\r\n\r\n", $wire, 2);
    $lines = explode("\r\n", $block[0]);
    array_shift($lines);
    return $lines;
}

// Every request Snoopy writes has to be one a server can read: a request line
// of three parts whose target carries no whitespace and no control character,
// and field lines that are a name and a value with one host named.
function requestsAreWellFormed($requests)
{
    foreach ($requests as $index => $wire) {
        $shape = requestLineShape($wire);
        redirectTargetAssert($shape['spaces'] === 2,
            'Request ' . $index . ' must have a request line of three parts: '
            . var_export($shape['line'], true));
        redirectTargetAssert(is_string($shape['target']),
            'Request ' . $index . ' must name a target: ' . var_export($shape['line'], true));
        redirectTargetAssert(preg_match('/[[:space:][:cntrl:]]/', $shape['target']) !== 1,
            'Request ' . $index . ' must carry no whitespace and no control character in its target: '
            . var_export($shape['target'], true));
        foreach (redirectFieldLines($wire) as $line) {
            redirectTargetAssert(preg_match('/^[A-Za-z0-9-]+: [^[:cntrl:]]*$/D', $line) === 1,
                'Request ' . $index . ' field line must be a name and a value: ' . var_export($line, true));
            // A host name carries no whitespace, and the redirect target is
            // where the Host header of the request after it comes from.
            if (stripos($line, 'Host: ') === 0)
                redirectTargetAssert(preg_match('/[[:space:]]/', substr($line, 6)) !== 1,
                    'Request ' . $index . ' must name one host: ' . var_export($line, true));
        }
    }
}

// A self-signed certificate for 127.0.0.1, so the curl transport can be driven
// over the scheme that selects it. Generated in process: the suite needs no
// fixture on disk and no certificate authority.
function redirectTargetCertificate()
{
    static $path = null;
    if ($path !== null) return $path;
    redirectTargetAssert(extension_loaded('openssl'), 'The openssl extension is loaded');
    $key = openssl_pkey_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA));
    redirectTargetAssert($key !== false, 'A key is generated');
    $request = openssl_csr_new(array('commonName' => '127.0.0.1'), $key, array('digest_alg' => 'sha256'));
    redirectTargetAssert($request !== false, 'A certificate request is generated');
    $certificate = openssl_csr_sign($request, null, $key, 2, array('digest_alg' => 'sha256'));
    redirectTargetAssert($certificate !== false, 'The certificate is signed');
    openssl_x509_export($certificate, $pem);
    openssl_pkey_export($key, $private);
    while (openssl_error_string() !== false) ;
    $path = FileUtil::getTempFilename('redirect-target-tls', 'pem');
    redirectTargetAssert(file_put_contents($path, $pem . $private) > 0, 'The certificate is written');
    register_shutdown_function(function () use ($path) { @unlink($path); });
    return $path;
}

// A peer that keeps every byte of every request it is sent, so "what did
// Snoopy write" is answered by the bytes and not by a variable. Over TLS it is
// the peer the curl transport is reached through, the scheme being what
// selects that transport.
function redirectTargetPeer(array $responses, $tls, callable $body)
{
    $worker = <<<'PHP'
$certificate = $argv[2];
$context = stream_context_create($certificate === ''
    ? array()
    : array('ssl' => array('local_cert' => $certificate, 'allow_self_signed' => true, 'verify_peer' => false)));
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error,
    STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
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
    if ($certificate !== ''
        && @stream_socket_enable_crypto($peer, true, STREAM_CRYPTO_METHOD_TLS_SERVER) !== true) {
        fclose($peer);
        continue;
    }
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
        base64_encode(json_encode($responses)), $tls ? redirectTargetCertificate() : ''),
        array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
    redirectTargetAssert(is_resource($process), 'The local HTTP peer starts');
    try {
        stream_set_timeout($pipes[1], 15);
        $address = fgets($pipes[1]);
        redirectTargetAssert(is_string($address)
            && preg_match('/^127\.0\.0\.1:([0-9]+)\s*$/D', $address, $match) === 1,
            'The peer announces its local port');
        $client = new Snoopy();
        $client->proxy_host = $client->proxy_port = '';
        $client->proxy_proto = 'http';
        $client->IP = null;
        // php/util.php loads conf/config.php, so the configuration of the
        // install the suite is run from reaches this client. With the
        // private-network guard on, fetch() refuses the loopback peer before
        // connecting and the failure reads as the peer's.
        $client->block_private = false;
        $client->verify_certificates = false;
        $client->read_timeout = 2;
        $client->_fp_timeout = 3;
        $client->use_gzip = false;
        $check = $body($client, ($tls ? 'https' : 'http') . '://127.0.0.1:' . $match[1]);
        fwrite($pipes[0], "done\n");
        $json = '';
        while (($line = fgets($pipes[1])) !== false) $json .= $line;
        $requests = json_decode(trim($json), true);
        redirectTargetAssert(is_array($requests), 'The peer reports the requests it received');
        $check(array_map('base64_decode', $requests));
    } finally {
        foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
        proc_close($process);
    }
}

function redirectWith($header)
{
    return array('wire' => base64_encode("HTTP/1.0 302 Found\r\n" . $header . "\r\n\r\n"));
}

$ok = array('wire' => base64_encode("HTTP/1.0 200 OK\r\nContent-Length: 2\r\n\r\nok"));

// A target no request line can carry names no redirect: the response is
// answered with itself and nothing more goes on the wire. Truncating at the
// offending byte would send a request for whatever came before it, which is a
// different resource and not a refusal, so one request is the whole chain.
$rejected = array(
    // A space in the target gives a request line of four parts. No server
    // reads the tail as a second request, but none accepts the line either.
    'a space' => 'Location: /next HTTP/1.0',
    'a space in the middle of a path' => 'Location: /two words',
    'a space in the query' => 'Location: /next?a=b c',
    'a tab' => "Location: /next\tHTTP/1.0",
    // parse_url() turns a control character into "_", but whatever the
    // attacker put behind it stays, spaces included.
    'a CR and a header behind it' => "Location: /next\rX-Injected: 1",
    // Whether a response with a NUL in a header line is a response at all is
    // curl's call and its versions differ: 8.14 refuses it with error 8,
    // 7.74 passes it through. Either is the response the caller is given.
    'a NUL' => array("Location: /next\x00X-Injected: 1", array(302, 8)),
    'a DEL byte' => "Location: /next\x7fX-Injected: 1",
    // The host half of the target is written into the Host header, which
    // names one host and cannot name a host with a space in it at all.
    'a space in the host' => 'Location: //evil host:{PORT}/next',
    'a space in the host of an absolute url' => 'Location: http://evil host:{PORT}/next',
    // The other two spellings feed the same variable.
    'a space through URI' => 'URI: /next HTTP/1.0',
    'a space through Refresh' => 'Refresh: 0; url=/next HTTP/1.0',
    // A header that names no target names no redirect either: the root of the
    // host is not what the response asked for.
    'no target at all' => 'URI:',
    'no target after Location' => 'Location:',
);

// The bound may not cost a redirect that works. These are legal targets, and
// each has to be followed and asked for exactly as it is written. An LF ends
// the header it is in, so the bytes behind one are a header of their own and
// the target before it is intact.
$kept = array(
    'a path' => array('Location: /next', '/next'),
    'a path with a query' => array('Location: /next?a=b&c=d', '/next?a=b&c=d'),
    'an encoded space' => array('Location: /two%20words', '/two%20words'),
    'a percent sign' => array('Location: /100%25', '/100%25'),
    'a header behind an LF' => array("Location: /next\nX-Injected: 1", '/next'),
);

// The curl transport hands the url to curl as a command-line argument, and
// escapeshellarg() keeps only the bytes that form a character in the process
// locale, so under LC_CTYPE=C a path written in UTF-8 arrives at curl without
// it. That is the url's own exposure, not the redirect capture's, so the case
// is measured on the transport that writes the request itself.
$keptOverSocket = array(
    'raw UTF-8 in the path' => array("Location: /caf\xc3\xa9", "/caf\xc3\xa9"),
);

// The socket transport and the curl transport capture a redirect in two
// separate blocks and write the request after it in two separate ways, so a
// bound only holds where it is measured. The scheme is what picks the
// transport, so the curl cases are the ones the peer answers over TLS.
$transports = array('the socket transport' => false, 'the curl transport' => true);

$tests = array();
foreach ($transports as $transport => $tls) {
    foreach ($rejected as $label => $case) {
        $header = is_array($case) ? $case[0] : $case;
        $status = (is_array($case) && $tls) ? $case[1] : array(302);
        $status = is_array($status) ? $status : array($status);
        $tests['a redirect target with ' . $label . ' names no redirect over ' . $transport] =
            function () use ($header, $status, $ok, $tls) {
                redirectTargetPeer(array(redirectWith($header), $ok), $tls, function ($client, $base) use ($status) {
                    $client->fetch($base . '/start');
                    // A response naming a redirect this client will not write
                    // is still that response, and is what the caller reads.
                    // Declining the target keeps it; refusing the url instead,
                    // which is what curl does when handed one, reports a
                    // transport error and loses the response it came with.
                    redirectTargetAssert(in_array((int) $client->status, $status, true),
                        'The caller is given the response that named the redirect: '
                        . $client->status . ' ' . $client->error);
                    return function ($requests) {
                        requestsAreWellFormed($requests);
                        redirectTargetAssert(count($requests) === 1,
                            'The chain is the one request: ' . var_export($requests, true));
                        foreach ($requests as $wire)
                            redirectTargetAssert(strpos($wire, 'X-Injected') === false,
                                'Nothing behind the offending byte reaches the wire: '
                                . var_export($wire, true));
                    };
                });
            };
    }
    foreach ($tls ? $kept : array_merge($kept, $keptOverSocket) as $label => $pair) {
        $tests['a redirect to ' . $label . ' is still followed over ' . $transport] =
            function () use ($pair, $ok, $tls) {
                list($header, $expected) = $pair;
                redirectTargetPeer(array(redirectWith($header), $ok), $tls,
                    function ($client, $base) use ($expected) {
                        redirectTargetAssert($client->fetch($base . '/start') && $client->results === 'ok',
                            'The redirect is followed to its answer: ' . $client->error);
                        return function ($requests) use ($expected) {
                            redirectTargetAssert(count($requests) === 2, 'The chain is two requests long');
                            requestsAreWellFormed($requests);
                            $shape = requestLineShape($requests[1]);
                            // curl writes a byte over 0x7f in a path as %XX
                            // of its own accord, which is the same target.
                            redirectTargetAssert($shape['target'] === $expected
                                || rawurldecode($shape['target']) === $expected,
                                'The second request asks for the target as written: '
                                . var_export($shape['target'], true) . ' for ' . var_export($expected, true));
                            redirectTargetAssert(strpos($requests[1], 'X-Injected') === false,
                                'No header the response carried is written into the request');
                        };
                    });
            };
    }
}

$failures = 0;
foreach ($tests as $name => $test) {
    try { $test(); echo 'ok - ', $name, "\n"; }
    catch (Throwable $error) { ++$failures; echo 'not ok - ', $name, ': ', $error->getMessage(), "\n"; }
}
echo count($tests), ' tests, ', $failures, " failures\n";
exit($failures ? 1 : 0);
