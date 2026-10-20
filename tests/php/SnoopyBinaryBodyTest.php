<?php

require_once(__DIR__ . '/../../php/Snoopy.class.inc');

function binaryBodyAssert($condition, $message)
{
    if (!$condition) throw new RuntimeException($message);
}

// A peer that reads whatever arrives rather than exactly as many bytes as the
// request declared, and reports both numbers. A transport that sends fewer
// bytes than its own Content-Length is then a byte count in the failure
// message instead of a read that never completes.
function binaryBodyRequest($curl, $body)
{
    $peer = <<<'PHP'
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($server === false) exit(2);
fwrite(STDOUT, stream_socket_get_name($server, false) . "\n");
fflush(STDOUT);
$socket = @stream_socket_accept($server, 10);
if ($socket === false) exit(3);
stream_set_blocking($socket, false);
$headers = '';
$deadline = microtime(true) + 4;
while (microtime(true) < $deadline && strpos($headers, "\r\n\r\n") === false) {
    $chunk = @fread($socket, 65536);
    if ($chunk === false) break;
    if ($chunk === '') { usleep(10000); continue; }
    $headers .= $chunk;
}
$split = explode("\r\n\r\n", $headers, 2);
$body = isset($split[1]) ? $split[1] : '';
$length = preg_match('/^Content-Length: *([0-9]+)\r?$/mi', $split[0], $match) ? (int)$match[1] : 0;
$deadline = microtime(true) + 3;
while (strlen($body) < $length && microtime(true) < $deadline) {
    $chunk = @fread($socket, $length - strlen($body));
    if ($chunk === false) break;
    if ($chunk === '') { usleep(10000); continue; }
    $body .= $chunk;
}
stream_set_blocking($socket, true);
@fwrite($socket, "HTTP/1.0 200 OK\r\nContent-Length: 2\r\nConnection: close\r\n\r\nok");
fclose($socket);
fclose($server);
echo json_encode(array('declared' => $length, 'body' => base64_encode($body)));
PHP;
    $process = proc_open(array(PHP_BINARY, '-c', __DIR__ . '/../php-test.ini', '-r', $peer),
        array(0 => array('file', '/dev/null', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
    binaryBodyAssert(is_resource($process), 'The loopback peer starts');
    try {
        stream_set_timeout($pipes[1], 20);
        $address = trim((string)fgets($pipes[1]));
        binaryBodyAssert(preg_match('/^127\.0\.0\.1:[0-9]+$/D', $address) === 1,
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
        $url = 'http://' . $address . '/body';
        if ($curl) {
            // _httpsrequest is the curl transport; HTTP keeps the peer local
            // without introducing a certificate fixture unrelated to body bytes.
            $client->host = '127.0.0.1';
            $client->port = parse_url($url, PHP_URL_PORT);
            $client->_httpsrequest($url, 'text/plain', $body, 'POST');
        } else {
            $client->fetch($url, 'POST', 'text/plain', $body);
        }
        $capture = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        unset($pipes[1]);
        fclose($pipes[2]);
        unset($pipes[2]);
        proc_close($process);
        $process = null;
        $request = json_decode($capture, true);
        binaryBodyAssert(is_array($request) && isset($request['declared'], $request['body']),
            'The peer reports what it received');
        return array('declared' => $request['declared'], 'received' => base64_decode($request['body']),
            'status' => $client->status, 'error' => $client->error);
    } finally {
        foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
        if (is_resource($process)) {
            proc_terminate($process);
            proc_close($process);
        }
    }
}

// Whatever is installed. LC_CTYPE is what decides which byte sequences are a
// character, so it is the setting a body's bytes depend on; a name the host
// does not have is reported rather than skipped silently.
function binaryBodyLocales()
{
    $found = array();
    foreach (array('C', 'C.UTF-8', 'C.utf8', 'POSIX', 'en_US.UTF-8', 'en_US.utf8') as $name) {
        $previous = setlocale(LC_CTYPE, '0');
        if (setlocale(LC_CTYPE, $name) !== false) $found[$name] = true;
        setlocale(LC_CTYPE, $previous);
    }
    return array_keys($found);
}

$tests = array(
    // A request body is bytes. The socket transport writes them to the socket
    // itself; the curl transport passes them to another program, and what a
    // byte means on the way there depends on LC_CTYPE. The peer's count is
    // the end condition: both transports must deliver as many bytes as the
    // request declared, and the same ones.
    'both transports deliver a binary body whole in every installed locale' => function () {
        $bodies = array(
            'a sequence that is no character in any locale' => "name=\xc3\xa9\x80\xfe\xff",
            'every byte from 0x80 up' => 'name=' . implode('', array_map('chr', range(0x80, 0xff))),
            'plain ascii' => 'plain=body&value=0',
        );
        $locales = binaryBodyLocales();
        binaryBodyAssert(count($locales) > 0, 'At least one locale is installed');
        echo '# locales exercised: ', implode(', ', $locales), "\n";
        $previous = setlocale(LC_CTYPE, '0');
        try {
            foreach ($locales as $locale) {
                binaryBodyAssert(setlocale(LC_CTYPE, $locale) !== false, 'LC_CTYPE is set to ' . $locale);
                foreach ($bodies as $label => $body) {
                    $sent = array();
                    foreach (array(false => 'socket', true => 'curl') as $curl => $transport) {
                        $result = binaryBodyRequest($curl, $body);
                        $where = $transport . ' under ' . $locale . ' for ' . $label;
                        binaryBodyAssert($result['declared'] === strlen($body),
                            'The request must declare ' . strlen($body) . ' bytes: ' . $where
                            . ' declared ' . $result['declared']);
                        binaryBodyAssert(strlen($result['received']) === strlen($body),
                            'The peer must receive as many bytes as the request declared: ' . $where
                            . ' sent ' . strlen($result['received']) . ' of ' . strlen($body)
                            . ' (status ' . $result['status'] . ' ' . $result['error'] . ')');
                        binaryBodyAssert($result['received'] === $body,
                            'The peer must receive the body unchanged: ' . $where . ' received '
                            . bin2hex($result['received']) . ' for ' . bin2hex($body));
                        $sent[$transport] = $result['received'];
                    }
                    binaryBodyAssert($sent['socket'] === $sent['curl'],
                        'The transports must agree on the bytes of ' . $label . ' under ' . $locale);
                }
            }
        } finally {
            setlocale(LC_CTYPE, $previous);
        }
    },
    // A body the curl transport hands over through a file leaves no file
    // behind, and leaves none a second user on the host could have read.
    'the curl transport leaves no readable copy of the body behind' => function () {
        $directory = FileUtil::getTempDirectory();
        $before = glob($directory . 'rutorrent-*');
        $result = binaryBodyRequest(true, "secret=\xc3\xa9\x80\xfe\xff&password=s3cret");
        binaryBodyAssert(strlen($result['received']) === $result['declared'],
            'The request completes with the body it declared');
        $after = array_diff(glob($directory . 'rutorrent-*'), $before === false ? array() : $before);
        binaryBodyAssert($after === array(),
            'No temporary file outlives the request: ' . implode(', ', $after));
    },
    // What the body is handed over as, observed from the other side: a
    // wrapper records the arguments curl is called with and the mode of any
    // file among them. A body on the command line is readable in the process
    // table, and a body in a file every user can read is no better.
    'the body is handed over as a file no other user can read' => function () {
        $directory = FileUtil::getTempDirectory();
        $record = $directory . 'binary-body-record-' . getmypid();
        $wrapper = $directory . 'binary-body-curl-' . getmypid();
        $template = <<<'SH'
#!/bin/sh
for a in "$@"; do
	printf '%s\n' "$a" >> {ARGV}
	case "$a" in
		@/*) stat -c '%a' "${a#@}" >> {MODES} ;;
	esac
done
exec curl "$@"

SH;
        $script = str_replace(array('{ARGV}', '{MODES}'),
            array(escapeshellarg($record . '.argv'), escapeshellarg($record)), $template);
        binaryBodyAssert(file_put_contents($wrapper, $script) === strlen($script), 'The wrapper is written');
        binaryBodyAssert(chmod($wrapper, 0700), 'The wrapper is executable');
        $previous = isset($GLOBALS['pathToExternals']['curl']) ? $GLOBALS['pathToExternals']['curl'] : null;
        $GLOBALS['pathToExternals']['curl'] = $wrapper;
        $body = "secret=\xc3\xa9\x80\xfe\xff&password=s3cret";
        try {
            $result = binaryBodyRequest(true, $body);
            binaryBodyAssert($result['received'] === $body, 'The body reaches the peer whole');
            $arguments = file_exists($record . '.argv')
                ? explode("\n", rtrim(file_get_contents($record . '.argv'), "\n")) : array();
            binaryBodyAssert(count($arguments) > 0, 'The wrapper recorded what curl was called with');
            foreach ($arguments as $argument)
                binaryBodyAssert(strpos($argument, 'password=s3cret') === false,
                    'No argument carries the body: ' . $argument);
            $modes = file_exists($record) ? explode("\n", trim(file_get_contents($record))) : array();
            binaryBodyAssert($modes === array('600'),
                'The body file is readable by its owner only: ' . implode(', ', $modes));
        } finally {
            if ($previous === null) unset($GLOBALS['pathToExternals']['curl']);
            else $GLOBALS['pathToExternals']['curl'] = $previous;
            foreach (array($wrapper, $record, $record . '.argv') as $path)
                if (file_exists($path)) unlink($path);
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
