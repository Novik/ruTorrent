<?php

require_once(__DIR__ . '/../../php/Snoopy.class.inc');

function urlBytesAssert($condition, $message)
{
    if (!$condition) throw new RuntimeException($message);
}

// A peer that answers one request and hands back the request line it read.
// The target on that line is the resource as far as a server is concerned, so
// it is what a transport has to get right -- not anything the client reports
// about itself once the request is over.
function urlBytesTarget($curl, $target)
{
    $peer = <<<'PHP'
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($server === false) exit(2);
fwrite(STDOUT, stream_socket_get_name($server, false) . "\n");
fflush(STDOUT);
$socket = @stream_socket_accept($server, 10);
if ($socket === false) exit(3);
stream_set_timeout($socket, 5);
$request = '';
while (($line = fgets($socket)) !== false) {
    $request .= $line;
    if ($line === "\r\n") break;
}
@fwrite($socket, "HTTP/1.0 200 OK\r\nContent-Length: 2\r\nConnection: close\r\n\r\nok");
fclose($socket);
fclose($server);
echo json_encode(array('request' => base64_encode($request)));
PHP;
    $process = proc_open(array(PHP_BINARY, '-c', __DIR__ . '/../php-test.ini', '-r', $peer),
        array(0 => array('file', '/dev/null', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
    urlBytesAssert(is_resource($process), 'The loopback peer starts');
    try {
        stream_set_timeout($pipes[1], 20);
        $address = trim((string) fgets($pipes[1]));
        urlBytesAssert(preg_match('/^127\.0\.0\.1:[0-9]+$/D', $address) === 1,
            'The peer announces its listener');
        $client = new Snoopy();
        $client->proxy_host = $client->proxy_port = '';
        $client->proxy_proto = 'http';
        $client->IP = null;
        // php/util.php loads conf/config.php, so the configuration of the
        // install the suite is run from reaches this client. With the
        // private-network guard on, fetch() refuses the loopback peer before
        // connecting and the failure reads as the peer's.
        $client->block_private = false;
        $client->read_timeout = 3;
        $url = 'http://' . $address . $target;
        if ($curl) {
            // _httpsrequest is the curl transport; HTTP keeps the peer local
            // without introducing a certificate fixture unrelated to the
            // bytes of a url.
            $client->host = '127.0.0.1';
            $client->port = (int) substr(strrchr($address, ':'), 1);
            $client->_httpsrequest($url);
        } else {
            $client->fetch($url);
        }
        $capture = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        unset($pipes[1]);
        fclose($pipes[2]);
        unset($pipes[2]);
        proc_close($process);
        $process = null;
        $request = json_decode($capture, true);
        urlBytesAssert(is_array($request) && isset($request['request']),
            'The peer reports the request it read');
        $line = strtok(base64_decode($request['request']), "\r\n");
        urlBytesAssert(is_string($line) && preg_match('|^[A-Z]+ (\S*) HTTP/|', $line, $match) === 1,
            'The peer read a request line: ' . var_export($line, true));
        return $match[1];
    } finally {
        foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
        if (is_resource($process)) {
            proc_terminate($process);
            proc_close($process);
        }
    }
}

// Whatever is installed. LC_CTYPE is what decides which byte sequences are a
// character, so it is the setting the bytes of a url depend on; a name the
// host does not have is reported rather than skipped silently.
function urlBytesLocales()
{
    $found = array();
    foreach (array('C', 'C.UTF-8', 'C.utf8', 'POSIX', 'en_US.UTF-8', 'en_US.utf8') as $name) {
        $previous = setlocale(LC_CTYPE, '0');
        if (setlocale(LC_CTYPE, $name) !== false) $found[$name] = true;
        setlocale(LC_CTYPE, $previous);
    }
    return array_keys($found);
}

$targets = array(
    'a path spelt in UTF-8' => "/caf\xc3\xa9",
    'a path of bytes that are no character in any locale' => "/caf\xe9\x80\xfe\xff",
    'a non-ASCII query' => "/x?q=\xc3\xa9&r=1",
    'a path already percent-encoded' => '/caf%C3%A9',
    'plain ASCII' => '/plain?q=1',
);

$tests = array(
    // A url names a resource. The socket transport writes the target it was
    // given to the socket itself; the curl transport passes it to another
    // program, and what a byte means on the way there depends on LC_CTYPE.
    // The peer's request line is the end condition: both transports have to
    // ask for the same resource, whatever the locale, and a target that
    // reaches curl with bytes still in it is one the next locale can change.
    'both transports name the same resource in every installed locale' => function () use ($targets) {
        $locales = urlBytesLocales();
        urlBytesAssert(count($locales) > 0, 'At least one locale is installed');
        echo '# locales exercised: ', implode(', ', $locales), "\n";
        $previous = setlocale(LC_CTYPE, '0');
        try {
            foreach ($locales as $locale) {
                urlBytesAssert(setlocale(LC_CTYPE, $locale) !== false, 'LC_CTYPE is set to ' . $locale);
                foreach ($targets as $label => $target) {
                    $where = $label . ' under ' . $locale;
                    $socket = urlBytesTarget(false, $target);
                    urlBytesAssert($socket === $target,
                        'The socket transport asks for the target it was given: ' . $where
                        . ' asked for ' . bin2hex($socket) . ' of ' . bin2hex($target));
                    $curl = urlBytesTarget(true, $target);
                    urlBytesAssert(rawurldecode($curl) === rawurldecode($socket),
                        'The curl transport must ask for the same resource: ' . $where
                        . ' asked for ' . bin2hex($curl) . ' where the socket asked for ' . bin2hex($socket));
                    urlBytesAssert(preg_match('/[\x80-\xff]/', $curl) !== 1,
                        'No byte of the target is left for the locale to decide: ' . $where
                        . ' asked for ' . bin2hex($curl));
                }
            }
        } finally {
            setlocale(LC_CTYPE, $previous);
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
