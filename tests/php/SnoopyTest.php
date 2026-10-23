<?php

// Deliberately not using tests/plugins/rutracker_check/TestLib.php here:
// Snoopy.class.inc transitively loads php/settings.php -> php/xmlrpc.php,
// whose real rXMLRPC* classes collide with TestLib's doubles in either
// require order. A minimal local runner keeps the real classes intact.
require_once(__DIR__ . '/../../php/Snoopy.class.inc');

// php/util.php loads conf/config.php, so the configuration of the install the
// suite is run from decides what a plain Snoopy starts out as. The cases below
// measure the class, and the ones about the private-network guard turn it on
// for the client they test, so hold it off here rather than inherit it.
$GLOBALS['httpBlockPrivateNetworks'] = false;
$GLOBALS['httpPrivateNetworkAllowlist'] = array();

function snoopyAssertTrue($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function snoopyAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true)
        );
    }
}

function snoopyCurlArgs()
{
    return file(getenv('SNOOPY_TEST_ARGS'), FILE_IGNORE_NEW_LINES);
}

// Fake curl: records every argument, then fabricates a successful response.
// $SNOOPY_TEST_ARGS holds the arguments of the LAST invocation only, so a
// redirect test reads the request Snoopy made after following the redirect.
// With $SNOOPY_TEST_REDIRECT set, the first invocation answers 302 with that
// Location instead; $SNOOPY_TEST_SEEN is how the script remembers it did.
// $SNOOPY_TEST_BODY names a file whose bytes become the response body;
// $SNOOPY_TEST_NO_BODY leaves no body file at all, as curl does when the
// answer has no body. An argument naming a file with a leading @ is read the
// way curl reads one, so the record holds the request body itself.
$curlPath = tempnam(sys_get_temp_dir(), 'snoopy-curl-');
$argsPath = tempnam(sys_get_temp_dir(), 'snoopy-args-');
$bodyPath = tempnam(sys_get_temp_dir(), 'snoopy-body-');
$seenPath = sys_get_temp_dir() . '/snoopy-seen-' . getmypid();
$script = <<<'SH'
#!/bin/sh
: > "$SNOOPY_TEST_ARGS"
header_file=
body_file=
while [ "$#" -gt 0 ]; do
	printf '%s\n' "$1" >> "$SNOOPY_TEST_ARGS"
	case "$1" in
		-D)
			shift
			header_file=$1
			;;
		-o)
			shift
			body_file=$1
			;;
		# curl reads a request body named with a leading @ out of that
		# file, so the record carries the bytes it was handed and not
		# only the name of the file they came in.
		@/*)
			if [ -f "${1#@}" ]; then
				cat "${1#@}" >> "$SNOOPY_TEST_ARGS"
				printf '\n' >> "$SNOOPY_TEST_ARGS"
			fi
			;;
	esac
	shift
done
if [ -n "$SNOOPY_TEST_REDIRECT" ] && [ ! -f "$SNOOPY_TEST_SEEN" ]; then
	: > "$SNOOPY_TEST_SEEN"
	printf 'HTTP/1.1 302 Found\r\nLocation: %s\r\n\r\n' "$SNOOPY_TEST_REDIRECT" > "$header_file"
else
	printf '%b\r\n' "${SNOOPY_TEST_RESPONSE:-HTTP/1.1 200 OK\r\n}" > "$header_file"
fi
if [ -n "$SNOOPY_TEST_NO_BODY" ]; then
	rm -f "$body_file"
elif [ -n "$SNOOPY_TEST_BODY" ]; then
	cat "$SNOOPY_TEST_BODY" > "$body_file"
else
	: > "$body_file"
fi
if [ -n "$SNOOPY_TEST_EXIT" ]; then
	exit "$SNOOPY_TEST_EXIT"
fi
SH;
file_put_contents($curlPath, $script);
chmod($curlPath, 0700);
putenv('SNOOPY_TEST_ARGS=' . $argsPath);
putenv('SNOOPY_TEST_SEEN=' . $seenPath);
$pathToExternals['curl'] = $curlPath;

function snoopyRespondWith($response)
{
    putenv('SNOOPY_TEST_RESPONSE=' . $response);
}

// Hostname resolution is stubbed so the SSRF tests never depend on live DNS.
// Literal addresses still go through the real path, so a redirect to one is
// judged on its own merits.
class SnoopyResolvesToPublic extends Snoopy
{
    static public function resolveHost($host)
    {
        if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false) {
            return parent::resolveHost($host);
        }
        return array('93.184.216.34');
    }
}

// Stands in for the connection on the plain-HTTP path, which writes its own
// request head to a socket instead of handing arguments to curl. Every
// connect() returns one end of a fresh socket pair whose far end already holds
// the next canned response with its write side shut down, so Snoopy reads a
// complete answer; what Snoopy wrote is read back from the far end afterwards.
class SnoopyOverSocketPair extends Snoopy
{
    public $requests = array();
    private $responses = array();
    private $farEnds = array();

    public function __construct($responses)
    {
        parent::__construct();
        $this->responses = $responses;
    }

    function connect()
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        if (!is_array($pair)) {
            return false;
        }
        list($near, $far) = $pair;
        $response = array_shift($this->responses);
        fwrite($far, is_null($response) ? "HTTP/1.1 200 OK\r\n\r\n" : $response);
        stream_socket_shutdown($far, STREAM_SHUT_WR);
        $this->farEnds[] = $far;
        return $near;
    }

    // fetch() closes the near end itself; the request bytes stay readable from
    // the far end until it is closed here.
    public function closeSockets()
    {
        foreach ($this->farEnds as $far) {
            stream_set_blocking($far, false);
            $this->requests[] = (string) stream_get_contents($far);
            fclose($far);
        }
        $this->farEnds = array();
    }
}

// Asks the origin rule directly, which is cheaper than arranging a redirect
// for each pair and says which pair failed.
class SnoopyOriginProbe extends Snoopy
{
    public function leavesOrigin($url, $redirect)
    {
        return $this->redirectLeavesOrigin(parse_url($url), $redirect);
    }
}

// One gzip member holding $size zero bytes, built a megabyte at a time so the
// suite never holds the inflated size itself: 64 MiB of zeros is about 64 KiB
// of gzip, which is the shape of answer that inflates past memory_limit.
function snoopyGzipZeros($size)
{
    $deflater = deflate_init(ZLIB_ENCODING_GZIP, array('level' => 9));
    $chunk = str_repeat("\0", 1048576);
    $encoded = '';
    while ($size > 0) {
        $piece = $size >= strlen($chunk) ? $chunk : substr($chunk, 0, $size);
        $size -= strlen($piece);
        $encoded .= deflate_add($deflater, $piece, $size > 0 ? ZLIB_NO_FLUSH : ZLIB_FINISH);
    }
    return $encoded;
}

function snoopyGzipResponse($body)
{
    return "HTTP/1.1 200 OK\r\nContent-Encoding: gzip\r\n\r\n" . $body;
}

// A loopback PHP server answering prepared gzip bodies on the plain-HTTP path,
// and a child PHP that fetches from it under a memory_limit of its own, so a
// body that inflates without bound ends the child and not this suite. The
// server command starts with exec so proc_terminate reaches PHP rather than
// the shell that started it. The router reads its directory off __FILE__
// because the suite runner rewrites __DIR__ in this file's text. Both
// children get the suite's php-test.ini, as the suite runner passes it, so
// a developer's system php.ini cannot change what they measure.
class SnoopyGzipServer
{
    const SUITE_INI = __DIR__ . '/../php-test.ini';
    public $address;
    private $directory;
    private $process = null;
    private $log;
    private $files = array();

    public function __construct($bodies)
    {
        $this->directory = tempnam(sys_get_temp_dir(), 'snoopy-gzip-');
        unlink($this->directory);
        mkdir($this->directory, 0700);
        foreach ($bodies as $name => $body) {
            $this->write($name . '.gz', $body);
        }
        $router = $this->write('router.php', <<<'PHP'
<?php
$file = dirname(__FILE__) . '/' . basename($_SERVER['REQUEST_URI']) . '.gz';
if (!is_file($file)) {
    http_response_code(404);
    exit;
}
header('Content-Type: application/octet-stream');
header('Content-Encoding: gzip');
readfile($file);
PHP
        );
        $this->log = $this->write('server.log', '');
        try {
            $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
            snoopyAssertTrue($socket !== false, 'Could not reserve a loopback port: ' . $error);
            $this->address = stream_socket_get_name($socket, false);
            fclose($socket);
            $this->process = proc_open(
                'exec ' . escapeshellarg(PHP_BINARY) . ' -c ' . escapeshellarg(self::SUITE_INI)
                . ' -S ' . escapeshellarg($this->address)
                . ' -t ' . escapeshellarg($this->directory) . ' ' . escapeshellarg($router),
                array(0 => array('pipe', 'r'), 1 => array('file', $this->log, 'a'), 2 => array('file', $this->log, 'a')),
                $pipes
            );
            snoopyAssertTrue(is_resource($this->process), 'Could not start the loopback server');
            fclose($pipes[0]);
            $deadline = microtime(true) + 5;
            do {
                $connection = @stream_socket_client('tcp://' . $this->address, $errno, $error, 0.1);
                if ($connection !== false) {
                    fclose($connection);
                    return;
                }
                usleep(10000);
            } while (microtime(true) < $deadline && proc_get_status($this->process)['running']);
            throw new RuntimeException('The loopback server did not become ready: ' . file_get_contents($this->log));
        } catch (Throwable $error) {
            $this->close();
            throw $error;
        }
    }

    private function write($name, $content)
    {
        $path = $this->directory . '/' . $name;
        file_put_contents($path, $content);
        $this->files[] = $path;
        return $path;
    }

    // What the child saw: fetch()'s answer, status, error and the body's
    // length and digest. A child that died first -- which on an unbounded
    // inflate is memory_limit ending it with a fatal error -- is reported with
    // everything it printed.
    public function fetchInChild($path, $memoryLimit, $maxlength = '')
    {
        $script = $this->write('fetch.php', <<<'PHP'
<?php
require_once($argv[1]);
$client = new Snoopy();
$client->block_private = false;
$client->read_timeout = 10;
if ($argv[3] !== '') {
    $client->maxlength = (int) $argv[3];
}
$fetched = $client->fetch($argv[2]);
echo json_encode(array(
    'fetched' => $fetched,
    'status' => $client->status,
    'error' => $client->error,
    'length' => is_string($client->results) ? strlen($client->results) : null,
    'sha1' => is_string($client->results) ? sha1($client->results) : null,
    'peak' => memory_get_peak_usage(true),
));
PHP
        );
        $process = proc_open(
            array(PHP_BINARY, '-c', self::SUITE_INI, '-d', 'memory_limit=' . $memoryLimit, $script,
                __DIR__ . '/../../php/Snoopy.class.inc', 'http://' . $this->address . $path, (string) $maxlength),
            array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
            $pipes
        );
        snoopyAssertTrue(is_resource($process), 'Could not start the fetching child');
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        $seen = json_decode($stdout, true);
        if (!is_array($seen)) {
            throw new RuntimeException(
                'The child exited ' . $exit . ' without answering; it printed: '
                . trim($stdout . "\n" . $stderr)
            );
        }
        return $seen;
    }

    public function close()
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
            $this->process = null;
        }
        foreach ($this->files as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
    }
}

$tests = array(
    'explicit HTTPS POST forwards -X POST to curl' => function () {
        $client = new Snoopy();
        snoopyAssertTrue(
            $client->fetch('https://example.test/resource', 'POST', 'application/x-www-form-urlencoded', ''),
            'HTTPS request did not complete through the curl test double'
        );
        $args = snoopyCurlArgs();
        $flag = array_search('-X', $args, true);
        snoopyAssertTrue($flag !== false, 'Explicit HTTPS method was not passed to curl');
        snoopyAssertSame(
            'POST',
            isset($args[$flag + 1]) ? $args[$flag + 1] : null,
            'Empty-body explicit POST request was not preserved'
        );
    },
    'legacy positional HTTPS request never adds -X' => function () {
        $client = new Snoopy();
        snoopyAssertTrue(
            $client->_httpsrequest('https://example.test/legacy', 'application/x-www-form-urlencoded', 'payload'),
            'Legacy positional HTTPS request did not complete'
        );
        $args = snoopyCurlArgs();
        snoopyAssertSame(
            false,
            array_search('-X', $args, true),
            'Legacy 3-argument call must leave the HTTP method to curl'
        );
        snoopyAssertTrue(
            in_array('Content-type: application/x-www-form-urlencoded', $args, true),
            'Legacy positional content-type argument remains supported'
        );
        snoopyAssertTrue(in_array('payload', $args, true), 'Legacy positional request body remains supported');
    },
    'explicit HTTPS GET with body keeps -X GET' => function () {
        $client = new Snoopy();
        snoopyAssertTrue(
            $client->fetch('https://example.test/get-with-body', 'GET', 'text/plain', 'payload'),
            'Explicit GET-with-body request did not complete'
        );
        $args = snoopyCurlArgs();
        $flag = array_search('-X', $args, true);
        snoopyAssertTrue(
            $flag !== false && isset($args[$flag + 1]) && $args[$flag + 1] === 'GET',
            'Explicit HTTPS GET method must not be changed to POST by curl -d'
        );
    },
    'private targets stay reachable while the guard is off' => function () {
        $client = new Snoopy();
        snoopyAssertTrue(
            $client->fetch('https://127.0.0.1/feed'),
            'Default configuration must not block loopback targets'
        );
    },
    'the guard blocks a literal private address' => function () {
        $client = new Snoopy();
        $client->block_private = true;
        snoopyAssertSame(false, $client->fetch('https://127.0.0.1/feed'), 'Loopback target was fetched anyway');
        snoopyAssertTrue(
            strpos($client->error, '127.0.0.1') !== false,
            'Blocked fetch must name the offending address, got: ' . $client->error
        );
    },
    'the guard blocks the IPv6 loopback literal' => function () {
        $client = new Snoopy();
        $client->block_private = true;
        snoopyAssertSame(false, $client->fetch('https://[::1]/feed'), 'IPv6 loopback target was fetched anyway');
    },
    'the guard leaves public literals alone' => function () {
        $client = new Snoopy();
        $client->block_private = true;
        snoopyAssertTrue($client->fetch('https://93.184.216.34/feed'), 'Public literal was blocked: ' . $client->error);
    },
    'the allowlist exempts a host from the guard' => function () {
        $client = new Snoopy();
        $client->block_private = true;
        $client->private_allowlist = array('127.0.0.1');
        snoopyAssertTrue($client->fetch('https://127.0.0.1/feed'), 'Allowlisted host was blocked: ' . $client->error);
    },
    'the guard blocks a hostname that resolves to loopback' => function () {
        $client = new Snoopy();
        $client->block_private = true;
        snoopyAssertSame(false, $client->fetch('https://localhost/feed'), 'localhost was fetched anyway');
    },
    'a host that cannot be resolved is blocked, and says so' => function () {
        $client = new Snoopy();
        $client->block_private = true;
        snoopyAssertSame(
            false,
            $client->fetch('https://tracker.nonexistent.invalid/feed'),
            'Unresolvable host was fetched anyway'
        );
        snoopyAssertTrue(
            stripos($client->error, 'resolve') !== false,
            'Unresolvable host must be reported as such, got: ' . $client->error
        );
    },
    'the validated address is pinned for the HTTPS request' => function () {
        $client = new SnoopyResolvesToPublic();
        $client->block_private = true;
        snoopyAssertTrue($client->fetch('https://tracker.test/feed'), 'Public host was blocked: ' . $client->error);
        $args = snoopyCurlArgs();
        $flag = array_search('--resolve', $args, true);
        snoopyAssertTrue($flag !== false, 'Validated host must be pinned with --resolve');
        snoopyAssertSame(
            'tracker.test:443:93.184.216.34',
            isset($args[$flag + 1]) ? $args[$flag + 1] : null,
            'Pinned address must be the one the guard validated'
        );
    },
    'the guard covers ranges filter_var calls public' => function () {
        $client = new Snoopy();
        $client->block_private = true;
        snoopyAssertSame(false, $client->fetch('https://100.64.0.1/feed'), 'Carrier-grade NAT target was fetched anyway');
        snoopyAssertSame(false, $client->fetch('https://192.0.0.1/feed'), 'IETF protocol assignment target was fetched anyway');
    },
    'the guard is configured from conf/config.php' => function () {
        $GLOBALS['httpBlockPrivateNetworks'] = true;
        $GLOBALS['httpPrivateNetworkAllowlist'] = array('127.0.0.1');
        $client = new Snoopy();
        unset($GLOBALS['httpBlockPrivateNetworks'], $GLOBALS['httpPrivateNetworkAllowlist']);
        snoopyAssertTrue($client->block_private, 'Configured guard was not picked up');
        snoopyAssertSame(false, $client->fetch('https://10.0.0.1/feed'), 'Configured guard did not block a private target');
        snoopyAssertTrue($client->fetch('https://127.0.0.1/feed'), 'Configured allowlist was not picked up: ' . $client->error);
    },
    'a redirect into a private address is blocked too' => function () {
        snoopyRespondWith('HTTP/1.1 302 Found\r\nLocation: http://127.0.0.1/secret\r\n');
        $client = new SnoopyResolvesToPublic();
        $client->block_private = true;
        snoopyAssertSame(
            false,
            $client->fetch('https://tracker.test/start'),
            'Redirect to a private address was followed'
        );
        snoopyAssertTrue(
            strpos($client->error, '127.0.0.1') !== false,
            'Blocked redirect must name the offending address, got: ' . $client->error
        );
    },
    // A Location like "//host/path" is a network-path reference (RFC 3986
    // 4.2): it carries its own authority and inherits only the scheme. Kinozal
    // answers exactly that to a guest download, and resolving it against the
    // requested host produced https://dl.kinozal.guru:443//kinozal.guru/... --
    // an address that redirects again, until maxredirs runs out.
    'protocol-relative redirect inherits the scheme and takes the new host' => function () use ($seenPath) {
        @unlink($seenPath);
        putenv('SNOOPY_TEST_REDIRECT=//kinozal.guru/login.php?to=%2Fdownload.php%3Fid%3D1');
        try {
            $client = new Snoopy();
            snoopyAssertTrue(
                $client->fetch('https://dl.kinozal.guru/download.php?id=1'),
                'Redirected HTTPS request did not complete'
            );
            $args = snoopyCurlArgs();
            snoopyAssertSame(
                'https://kinozal.guru/login.php?to=%2Fdownload.php%3Fid%3D1',
                end($args),
                'The redirect must be followed to the host it names'
            );
            snoopyAssertSame('200', $client->status, 'The redirect target answered');
        } finally {
            putenv('SNOOPY_TEST_REDIRECT');
            @unlink($seenPath);
        }
    },
    // Same rule on the plain-HTTP path, which parses its headers off the
    // socket instead of curl's dump file. A socket pair stands in for the
    // connection: the response is written from the far end, whose write side
    // is then shut down so Snoopy sees EOF while its own request still has
    // somewhere to go.
    'protocol-relative redirect inherits the scheme over plain HTTP' => function () {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        snoopyAssertTrue(is_array($pair), 'Unable to create the socket pair standing in for the connection');
        list($near, $far) = $pair;
        fwrite($far, "HTTP/1.1 302 Found\r\nLocation: //kinozal.guru/login.php?to=x\r\n\r\n");
        stream_socket_shutdown($far, STREAM_SHUT_WR);

        $client = new Snoopy();
        $client->host = 'dl.kinozal.guru';
        $client->port = 80;
        try {
            snoopyAssertTrue(
                $client->_httprequest('/download.php?id=1', $near, 'http://dl.kinozal.guru/download.php?id=1', 'GET'),
                'Plain HTTP request did not complete'
            );
        } finally {
            fclose($near);
            fclose($far);
        }
        snoopyAssertSame(
            'http://kinozal.guru/login.php?to=x',
            $client->_redirectaddr,
            'The redirect must be followed to the host it names'
        );
    },
    // A redirect names a host of its own choosing. What travelled with the
    // request was collected for the host that was asked -- basic credentials
    // from the url, the cookie jar fetchComplex loaded for that host, an
    // Authorization header a caller set -- so a redirect elsewhere must not
    // carry any of it.
    'a cross-host redirect drops the credentials' => function () use ($seenPath) {
        @unlink($seenPath);
        putenv('SNOOPY_TEST_REDIRECT=https://collector.test/landing');
        try {
            $client = new Snoopy();
            $client->cookies['session'] = 'secret-session';
            $client->rawheaders['Authorization'] = 'Bearer secret-token';
            snoopyAssertTrue(
                $client->fetch('https://user:pass@tracker.test/feed'),
                'Redirected HTTPS request did not complete'
            );
            $args = snoopyCurlArgs();
            snoopyAssertSame(
                'https://collector.test/landing',
                end($args),
                'The redirect must be followed to the host it names'
            );
            snoopyAssertSame(
                array(),
                array_values(array_filter($args, function ($arg) {
                    return stripos($arg, 'Authorization:') === 0
                        || stripos($arg, 'Cookie:') === 0;
                })),
                'Credentials were sent to the redirect target'
            );
            snoopyAssertSame('', $client->user, 'Basic user survived a cross-host redirect');
            snoopyAssertSame('', $client->pass, 'Basic password survived a cross-host redirect');
        } finally {
            putenv('SNOOPY_TEST_REDIRECT');
            @unlink($seenPath);
        }
    },
    'a redirect within the same host keeps the credentials' => function () use ($seenPath) {
        @unlink($seenPath);
        putenv('SNOOPY_TEST_REDIRECT=https://tracker.test/elsewhere');
        try {
            $client = new Snoopy();
            $client->cookies['session'] = 'secret-session';
            $client->rawheaders['Authorization'] = 'Bearer secret-token';
            snoopyAssertTrue(
                $client->fetch('https://user:pass@tracker.test/feed'),
                'Redirected HTTPS request did not complete'
            );
            $args = snoopyCurlArgs();
            snoopyAssertTrue(
                in_array('Authorization: Bearer secret-token', $args, true),
                'A caller header must survive a redirect on the same host'
            );
            snoopyAssertTrue(
                in_array('Cookie: session=secret-session', $args, true),
                'The cookie jar must survive a redirect on the same host'
            );
        } finally {
            putenv('SNOOPY_TEST_REDIRECT');
            @unlink($seenPath);
        }
    },
    // What the credentials were collected for is an origin -- scheme, host and
    // port together -- not a name. Each pair below is (requested, redirect,
    // whether that leaves the origin).
    'the origin rule reads the scheme, the host and the port' => function () {
        $probe = new SnoopyOriginProbe();
        $pairs = array(
            // The same origin, however it is spelled.
            array('https://tracker.test/feed', 'https://tracker.test/elsewhere', false),
            array('https://tracker.test/feed', 'https://TRACKER.test/elsewhere', false),
            array('https://tracker.test/feed', 'https://tracker.test:443/x', false),
            array('http://tracker.test:8080/feed', 'http://tracker.test:8080/x', false),
            array('https://tracker.test/feed', '/relative/path', false),
            // A different port is a different service, even on the same name.
            array('https://tracker.test/feed', 'https://tracker.test:8443/x', true),
            array('http://tracker.test/feed', 'http://tracker.test:8080/x', true),
            array('http://tracker.test:8080/feed', 'http://tracker.test/x', true),
            array('http://tracker.test:8080/feed', 'http://tracker.test:9090/x', true),
            // A different host, with and without a port to argue about.
            array('https://tracker.test/feed', 'https://collector.test/landing', true),
            array('https://tracker.test:8443/feed', 'https://collector.test:8443/x', true),
            // Off tls, which is where the credential would go out in clear.
            array('https://tracker.test/feed', 'http://tracker.test/feed', true),
            array('https://tracker.test:8443/feed', 'http://tracker.test:8443/x', true),
            // The one move that keeps them, and only in that plain form.
            array('http://tracker.test/feed', 'https://tracker.test/feed', false),
            array('http://tracker.test:8080/feed', 'https://tracker.test:8080/x', true),
        );
        foreach ($pairs as $pair) {
            list($url, $redirect, $expected) = $pair;
            snoopyAssertSame(
                $expected,
                $probe->leavesOrigin($url, $redirect),
                $url . ' -> ' . $redirect
            );
        }
    },
    // And on the wire, over the socket path, so the drop is read off the bytes
    // rather than off the predicate.
    'a redirect to another port on the same host drops the credentials' => function () {
        $client = new SnoopyOverSocketPair(array(
            "HTTP/1.1 302 Found\r\nLocation: http://tracker.test:8080/landing\r\n\r\n",
            "HTTP/1.1 200 OK\r\n\r\n",
        ));
        $client->cookies['session'] = 'secret-session';
        $client->rawheaders['Authorization'] = 'Bearer secret-token';
        $client->fetch('http://user:pass@tracker.test/feed');
        $client->closeSockets();

        snoopyAssertSame(2, count($client->requests), 'The redirect was not followed');
        snoopyAssertTrue(
            stripos($client->requests[0], "\r\nAuthorization:") !== false,
            'The first request should have carried the credentials'
        );
        snoopyAssertTrue(
            stripos($client->requests[1], "\r\nAuthorization:") === false,
            'An Authorization header was written to the other port'
        );
        snoopyAssertTrue(
            strpos($client->requests[1], 'secret-session') === false,
            'The cookie jar reached the other port'
        );
    },
    'a redirect staying on one port keeps the credentials' => function () {
        $client = new SnoopyOverSocketPair(array(
            "HTTP/1.1 302 Found\r\nLocation: http://tracker.test:8080/landing\r\n\r\n",
            "HTTP/1.1 200 OK\r\n\r\n",
        ));
        $client->cookies['session'] = 'secret-session';
        $client->rawheaders['Authorization'] = 'Bearer secret-token';
        $client->fetch('http://user:pass@tracker.test:8080/feed');
        $client->closeSockets();

        snoopyAssertSame(2, count($client->requests), 'The redirect was not followed');
        snoopyAssertTrue(
            stripos($client->requests[1], "\r\nAuthorization:") !== false,
            'A caller header must survive a redirect within one origin'
        );
        snoopyAssertTrue(
            strpos($client->requests[1], 'secret-session') !== false,
            'The cookie jar must survive a redirect within one origin'
        );
    },
    // Same host, but the redirect moves off tls. The second leg leaves the
    // curl path for the socket path, which the socket pair stands in for, so
    // nothing here opens a connection.
    'a redirect down to plain http drops the credentials' => function () use ($seenPath) {
        @unlink($seenPath);
        putenv('SNOOPY_TEST_REDIRECT=http://tracker.test/feed');
        try {
            $client = new SnoopyOverSocketPair(array("HTTP/1.1 200 OK\r\n\r\n"));
            $client->cookies['session'] = 'secret-session';
            $client->rawheaders['Authorization'] = 'Bearer secret-token';
            $client->fetch('https://tracker.test/feed');
            $client->closeSockets();
            snoopyAssertSame(1, count($client->requests), 'The redirect was not followed');
            snoopyAssertTrue(
                stripos($client->requests[0], "\r\nAuthorization:") === false,
                'An Authorization header went out over plain http'
            );
            snoopyAssertTrue(
                strpos($client->requests[0], 'secret-session') === false,
                'The cookie jar went out over plain http'
            );
        } finally {
            putenv('SNOOPY_TEST_REDIRECT');
            @unlink($seenPath);
        }
    },
    // The plain-HTTP path builds its own request head rather than handing
    // arguments to curl, so it is checked on the bytes it writes. connect() is
    // replaced by a socket pair per request: the far end already holds the
    // response with its write side shut, and what Snoopy wrote is read back
    // from it afterwards.
    'a cross-host redirect drops the credentials over plain http' => function () {
        $client = new SnoopyOverSocketPair(array(
            "HTTP/1.1 302 Found\r\nLocation: http://collector.test/landing\r\n\r\n",
            "HTTP/1.1 200 OK\r\n\r\n",
        ));
        $client->cookies['session'] = 'secret-session';
        $client->rawheaders['Authorization'] = 'Bearer secret-token';
        $client->fetch('http://user:pass@tracker.test/feed');
        $client->closeSockets();

        snoopyAssertSame(2, count($client->requests), 'The redirect was not followed');
        snoopyAssertTrue(
            stripos($client->requests[0], "\r\nAuthorization:") !== false,
            'The first request should have carried the credentials'
        );
        snoopyAssertTrue(
            stripos($client->requests[1], "\r\nAuthorization:") === false,
            'An Authorization header was written to the redirect target'
        );
        snoopyAssertTrue(
            stripos($client->requests[1], "\r\nCookie:") === false,
            'A Cookie header was written to the redirect target'
        );
        snoopyAssertTrue(
            strpos($client->requests[1], 'secret-session') === false,
            'The cookie jar reached the redirect target'
        );
    },
    // curl -k turns off certificate checking. It used to be appended to every
    // HTTPS fetch with no way to stop it, which made every feed, torrent
    // download and tracker login readable and changeable by anything on the
    // path.
    'HTTPS fetches check the certificate by default' => function () {
        $client = new Snoopy();
        snoopyAssertTrue($client->fetch('https://tracker.test/feed'), 'HTTPS request did not complete');
        snoopyAssertSame(
            false,
            array_search('-k', snoopyCurlArgs(), true),
            'Certificate checking was turned off without being asked'
        );
    },
    'turning the check off puts -k back' => function () {
        $client = new Snoopy();
        $client->verify_certificates = false;
        snoopyAssertTrue($client->fetch('https://tracker.test/feed'), 'HTTPS request did not complete');
        snoopyAssertTrue(
            array_search('-k', snoopyCurlArgs(), true) !== false,
            'An install that opts out must still reach a self-signed host'
        );
    },
    'the certificate check is configured from conf/config.php' => function () {
        $GLOBALS['httpVerifyCertificates'] = false;
        $client = new Snoopy();
        unset($GLOBALS['httpVerifyCertificates']);
        snoopyAssertSame(false, $client->verify_certificates, 'Configured opt-out was not picked up');
        snoopyAssertTrue($client->fetch('https://tracker.test/feed'), 'HTTPS request did not complete');
        snoopyAssertTrue(
            array_search('-k', snoopyCurlArgs(), true) !== false,
            'Configured opt-out did not reach curl'
        );
    },
    'the proxy leg is checked on the same terms' => function () {
        $client = new Snoopy();
        $client->proxy_host = '127.0.0.1';
        $client->proxy_port = 3128;
        snoopyAssertTrue($client->fetch('https://tracker.test/feed'), 'Proxied HTTPS request did not complete');
        $args = snoopyCurlArgs();
        snoopyAssertSame(
            false,
            array_search('--proxy-insecure', $args, true),
            'The proxy leg was made insecure while checking is on'
        );
        snoopyAssertTrue(
            array_search('--proxy', $args, true) !== false,
            'The proxy itself must still be passed to curl'
        );

        $client = new Snoopy();
        $client->verify_certificates = false;
        $client->proxy_host = '127.0.0.1';
        $client->proxy_port = 3128;
        snoopyAssertTrue($client->fetch('https://tracker.test/feed'), 'Proxied HTTPS request did not complete');
        snoopyAssertTrue(
            array_search('--proxy-insecure', snoopyCurlArgs(), true) !== false,
            'An install that opts out must still reach a self-signed proxy'
        );
    },
    // curl exits 60 when it cannot verify the peer. "error 60" on its own tells
    // an admin with a self-signed indexer nothing about what to do.
    'a certificate failure says what it was and how to opt out' => function () {
        putenv('SNOOPY_TEST_EXIT=60');
        try {
            $client = new Snoopy();
            snoopyAssertSame(false, $client->fetch('https://tracker.test/feed'), 'A failed fetch reported success');
            snoopyAssertTrue(
                stripos($client->error, 'certificate') !== false,
                'The failure must say it was the certificate, got: ' . $client->error
            );
            snoopyAssertTrue(
                strpos($client->error, 'httpVerifyCertificates') !== false,
                'The failure must name the setting that turns it off, got: ' . $client->error
            );
            snoopyAssertTrue(
                strpos($client->error, 'tracker.test') !== false,
                'The failure must name the host, got: ' . $client->error
            );
        } finally {
            putenv('SNOOPY_TEST_EXIT');
        }
    },
    'an ordinary curl failure is reported as before' => function () {
        putenv('SNOOPY_TEST_EXIT=7');
        try {
            $client = new Snoopy();
            snoopyAssertSame(false, $client->fetch('https://tracker.test/feed'), 'A failed fetch reported success');
            snoopyAssertSame(
                'Error: cURL could not retrieve the document, error 7.',
                $client->error,
                'A non-certificate failure must keep its wording'
            );
        } finally {
            putenv('SNOOPY_TEST_EXIT');
        }
    },
    // curl exits 35 for any failure in the TLS handshake, verification
    // included or not: a port answering something that is not TLS reaches it,
    // and so does a protocol or cipher mismatch. Naming the certificate there
    // sends an operator to install a certificate authority for a server that
    // presented no certificate at all.
    'a handshake failure is not reported as a certificate failure' => function () {
        putenv('SNOOPY_TEST_EXIT=35');
        try {
            $client = new Snoopy();
            snoopyAssertSame(false, $client->fetch('https://tracker.test/feed'), 'A failed fetch reported success');
            snoopyAssertSame(
                'Error: cURL could not retrieve the document, error 35.',
                $client->error,
                'A handshake failure must not be diagnosed as a certificate'
            );
        } finally {
            putenv('SNOOPY_TEST_EXIT');
        }
    },
    // The advice is to turn verification off. An install that has already
    // turned it off is told to do the thing it did, about a check that did
    // not run -- curl was given -k.
    'an install that already opted out is not told to opt out' => function () {
        foreach (array(35, 51, 60, 77) as $exit) {
            putenv('SNOOPY_TEST_EXIT=' . $exit);
            try {
                $client = new Snoopy();
                $client->verify_certificates = false;
                snoopyAssertSame(false, $client->fetch('https://tracker.test/feed'), 'A failed fetch reported success');
                snoopyAssertTrue(
                    strpos($client->error, 'httpVerifyCertificates') === false,
                    'With verification off, exit ' . $exit . ' must not name the setting, got: ' . $client->error
                );
            } finally {
                putenv('SNOOPY_TEST_EXIT');
            }
        }
    },
    // -k and --proxy-insecure are one setting, so either leg can be the one
    // that failed, and the exit code does not say which. The message names
    // the host it was fetching from; with a proxy in the way that host may
    // have presented no certificate at all.
    'a proxied certificate failure does not blame the origin alone' => function () {
        putenv('SNOOPY_TEST_EXIT=60');
        try {
            $client = new Snoopy();
            $client->proxy_host = '127.0.0.1';
            $client->proxy_port = 3128;
            $client->proxy_proto = 'https';
            snoopyAssertSame(false, $client->fetch('https://tracker.test/feed'), 'A failed fetch reported success');
            snoopyAssertTrue(
                stripos($client->error, 'proxy') !== false,
                'A proxied failure must say the proxy could be the one, got: ' . $client->error
            );
            snoopyAssertTrue(
                strpos($client->error, 'tracker.test') !== false,
                'and must still name the host it was fetching, got: ' . $client->error
            );
        } finally {
            putenv('SNOOPY_TEST_EXIT');
        }
    },
    // $maxlength bounds the compressed body at 4 MiB, and zeros compress
    // about 1000:1, so what a server sends in 64 KiB inflates to 64 MiB; the
    // child has 32 MiB, which is where memory_limit used to end the request
    // with a fatal error. The bound refuses the body instead, as a failed
    // fetch with a status below 100 and the reason in ->error.
    'a gzip body inflating past maxlength is refused before it fills memory' => function () {
        $server = new SnoopyGzipServer(array('zeros' => snoopyGzipZeros(64 * 1048576)));
        try {
            $seen = $server->fetchInChild('/zeros', '32M');
        } finally {
            $server->close();
        }
        snoopyAssertSame(false, $seen['fetched'], 'An oversized body was reported as fetched');
        snoopyAssertSame(Snoopy::RESPONSE_BODY_FAILED, $seen['status'], 'The refusal must carry its own status');
        snoopyAssertSame('invalid-or-oversized-gzip', $seen['error'], 'The refusal must say why');
        snoopyAssertSame(0, $seen['length'], 'A refused body must not be handed out in part');
    },
    'a small gzip body still decodes whole over plain http' => function () {
        $body = str_repeat("The quick brown fox jumps over the lazy dog.\n", 100);
        $server = new SnoopyGzipServer(array('small' => gzencode($body, 9)));
        try {
            $seen = $server->fetchInChild('/small', '32M');
        } finally {
            $server->close();
        }
        snoopyAssertSame(true, $seen['fetched'], 'A small gzip body was refused: ' . $seen['error']);
        snoopyAssertSame('200', $seen['status'], 'The status is still read off the response');
        snoopyAssertSame(strlen($body), $seen['length'], 'The body must come back whole');
        snoopyAssertSame(sha1($body), $seen['sha1'], 'The body must come back unchanged');
    },
    // The bound is $maxlength itself, on the inflated size: that many bytes
    // are a body, one more is not. Over the socket pair, in this process.
    'the bound is maxlength exactly: that many bytes decode, one more is refused' => function () {
        $exact = str_repeat('a', 100000);
        $client = new SnoopyOverSocketPair(array(
            snoopyGzipResponse(gzencode($exact, 9)),
            snoopyGzipResponse(gzencode($exact . 'a', 9)),
        ));
        $client->maxlength = 100000;
        try {
            snoopyAssertTrue($client->fetch('http://tracker.test/exact'), 'A body of exactly maxlength bytes was refused: ' . $client->error);
            snoopyAssertSame($exact, $client->results, 'A body of exactly maxlength bytes must come back whole');
            snoopyAssertSame(false, $client->fetch('http://tracker.test/over'), 'A body one byte past maxlength was accepted');
            snoopyAssertSame(Snoopy::RESPONSE_BODY_FAILED, $client->status, 'The refusal must carry its own status');
            snoopyAssertSame('invalid-or-oversized-gzip', $client->error, 'The refusal must say why');
            snoopyAssertSame('', $client->results, 'A refused body must not be handed out in part');
        } finally {
            $client->closeSockets();
        }
    },
    // A body that gzinflate cannot read used to come back as results === false
    // under a 200, which callers read as an empty page (bulk_magnet and the
    // RSS plugin then write a 0-byte .torrent and report it downloaded), and
    // the external-gzip fallback handed back exec()'s output array. It is
    // refused on the same terms as an oversized one: gzinflate's return value
    // does not say which, and both become one classified reason on purpose.
    'a body that is not gzip at all is refused rather than returned as false' => function () {
        $client = new SnoopyOverSocketPair(array(snoopyGzipResponse('this is not a gzip member')));
        try {
            snoopyAssertSame(false, $client->fetch('http://tracker.test/plain'), 'An unreadable body was reported as fetched');
            snoopyAssertSame(Snoopy::RESPONSE_BODY_FAILED, $client->status, 'The refusal must carry its own status');
            snoopyAssertSame('invalid-or-oversized-gzip', $client->error, 'The refusal must say why');
            snoopyAssertSame('', $client->results, 'A refused body must be empty, not false');
        } finally {
            $client->closeSockets();
        }
    },
    // The read stops at $maxlength and a dropped connection stops it anywhere,
    // so a body can arrive without its 8-byte trailer. gzinflate never looked
    // at the trailer, and a bound must not start to: the document is whole.
    'a gzip body cut after its deflate stream still decodes, as before' => function () {
        $body = str_repeat("hello world\n", 700);
        $client = new SnoopyOverSocketPair(array(snoopyGzipResponse(substr(gzencode($body, 9), 0, -8))));
        try {
            snoopyAssertTrue($client->fetch('http://tracker.test/cut'), 'A body without its trailer was refused: ' . $client->error);
            snoopyAssertSame($body, $client->results, 'A body without its trailer must still come back whole');
        } finally {
            $client->closeSockets();
        }
    },
    // A 304 or 204 may carry Content-Encoding: gzip with no body behind it
    // (RFC 9110 only says a 304 SHOULD NOT), and the RSS plugin sends
    // If-None-Match and reads a 304 as "unchanged". Such an answer is an empty
    // body under its own status, never a refusal; the base left results ===
    // false there. An empty gzip member is the same answer, spelled out.
    'a bodyless answer with a gzip header keeps its status and an empty body' => function () {
        $answers = array(
            '304' => "HTTP/1.1 304 Not Modified\r\nETag: \"x\"\r\nContent-Encoding: gzip\r\n\r\n",
            '204' => "HTTP/1.1 204 No Content\r\nContent-Encoding: gzip\r\n\r\n",
            '200' => "HTTP/1.1 200 OK\r\nContent-Encoding: gzip\r\n\r\n",
            '200 with an empty member' => snoopyGzipResponse(gzencode('', 9)),
        );
        $client = new SnoopyOverSocketPair(array_values($answers));
        try {
            foreach ($answers as $label => $answer) {
                $status = substr($label, 0, 3);
                snoopyAssertTrue($client->fetch('http://tracker.test/feed'), 'A bodyless ' . $label . ' with a gzip header was refused: ' . $client->error);
                snoopyAssertSame($status, $client->status, 'A bodyless ' . $label . ' must keep its status');
                snoopyAssertSame('', $client->results, 'A bodyless ' . $label . ' must be an empty string, not false');
            }
        } finally {
            $client->closeSockets();
        }
    },
    // The curl path decodes the same way, off the file curl wrote. The fake
    // curl copies $SNOOPY_TEST_BODY into it.
    'the curl path refuses an oversized gzip body too' => function () use ($bodyPath) {
        file_put_contents($bodyPath, snoopyGzipZeros(1048576));
        putenv('SNOOPY_TEST_BODY=' . $bodyPath);
        snoopyRespondWith('HTTP/1.1 200 OK\r\nContent-Encoding: gzip\r\n');
        try {
            $client = new Snoopy();
            $client->maxlength = 100000;
            snoopyAssertSame(false, $client->fetch('https://tracker.test/feed'), 'An oversized body was reported as fetched');
            snoopyAssertSame(Snoopy::RESPONSE_BODY_FAILED, $client->status, 'The refusal must carry its own status');
            snoopyAssertSame('invalid-or-oversized-gzip', $client->error, 'The refusal must say why');
            snoopyAssertSame('', $client->results, 'A refused body must not be handed out in part');
        } finally {
            putenv('SNOOPY_TEST_BODY');
            putenv('SNOOPY_TEST_RESPONSE');
        }
    },
    'the curl path decodes a small gzip body as before' => function () use ($bodyPath) {
        $body = str_repeat("The quick brown fox jumps over the lazy dog.\n", 100);
        file_put_contents($bodyPath, gzencode($body, 9));
        putenv('SNOOPY_TEST_BODY=' . $bodyPath);
        snoopyRespondWith('HTTP/1.1 200 OK\r\nContent-Encoding: gzip\r\n');
        try {
            $client = new Snoopy();
            snoopyAssertTrue($client->fetch('https://tracker.test/feed'), 'A small gzip body was refused: ' . $client->error);
            snoopyAssertSame('200', $client->status, 'The status is still read off the response');
            snoopyAssertSame($body, $client->results, 'The body must come back unchanged');
        } finally {
            putenv('SNOOPY_TEST_BODY');
            putenv('SNOOPY_TEST_RESPONSE');
        }
    },
    // Real curl writes no body file for a bodyless answer, so here the path
    // sees false where the socket path sees ''. The same rule applies.
    'the curl path keeps the status of a bodyless 304 with a gzip header' => function () {
        putenv('SNOOPY_TEST_NO_BODY=1');
        snoopyRespondWith('HTTP/1.1 304 Not Modified\r\nETag: "x"\r\nContent-Encoding: gzip\r\n');
        try {
            $client = new Snoopy();
            snoopyAssertTrue($client->fetch('https://tracker.test/feed'), 'A bodyless 304 was refused on the curl path: ' . $client->error);
            snoopyAssertSame('304', $client->status, 'A bodyless 304 must keep its status on the curl path');
            snoopyAssertSame('', $client->results, 'A bodyless 304 must be an empty string on the curl path, not false');
        } finally {
            putenv('SNOOPY_TEST_NO_BODY');
            putenv('SNOOPY_TEST_RESPONSE');
        }
    },
    // $httpMaxResponseLength is the operator's handle on the bound. A
    // conf/config.php that predates it keeps what the class ships with.
    'the bound is 4 MiB when conf/config.php says nothing' => function () {
        unset($GLOBALS['httpMaxResponseLength']);
        $client = new Snoopy();
        snoopyAssertSame(4194304, $client->maxlength, 'The shipped bound must survive an absent setting');
    },
    'the bound is configured from conf/config.php' => function () {
        $GLOBALS['httpMaxResponseLength'] = 100000;
        $client = new Snoopy();
        unset($GLOBALS['httpMaxResponseLength']);
        snoopyAssertSame(100000, $client->maxlength, 'Configured bound was not picked up');
    },
    // Anything that is not a positive byte count would refuse every fetch, and
    // an operator who wrote one did not mean that, so the shipped bound stands.
    'a bound that is not a positive byte count is ignored' => function () {
        foreach (array(0, -1, 'four megabytes') as $value) {
            $GLOBALS['httpMaxResponseLength'] = $value;
            $client = new Snoopy();
            unset($GLOBALS['httpMaxResponseLength']);
            snoopyAssertSame(
                4194304,
                $client->maxlength,
                'A bound of ' . var_export($value, true) . ' must leave the shipped one in place'
            );
        }
    },
    // Raising the bound is the point of the setting: a body the shipped 4 MiB
    // refuses comes back whole once an operator asks for more. Zeros keep the
    // wire small, so what is measured here is the inflated size.
    'a raised bound accepts a body the shipped one refuses' => function () {
        $size = 5 * 1048576;
        $encoded = snoopyGzipZeros($size);
        $refusing = new SnoopyOverSocketPair(array(snoopyGzipResponse($encoded)));
        try {
            snoopyAssertSame(false, $refusing->fetch('http://tracker.test/big'), 'The shipped bound accepted a 5 MiB body');
            snoopyAssertSame(Snoopy::RESPONSE_BODY_FAILED, $refusing->status, 'The refusal must carry its own status');
        } finally {
            $refusing->closeSockets();
        }
        $GLOBALS['httpMaxResponseLength'] = 8 * 1048576;
        $raised = new SnoopyOverSocketPair(array(snoopyGzipResponse($encoded)));
        unset($GLOBALS['httpMaxResponseLength']);
        try {
            snoopyAssertTrue($raised->fetch('http://tracker.test/big'), 'A body under the raised bound was refused: ' . $raised->error);
            snoopyAssertSame($size, strlen($raised->results), 'A body under the raised bound must come back whole');
        } finally {
            $raised->closeSockets();
        }
    },
    // The bound means one thing whatever the body arrived as: that many bytes
    // are a document, one more is not, and the one more is refused rather than
    // quietly dropped -- a caller handed a truncated body under a 200 cannot
    // tell it from a complete one.
    'a lowered bound is the same boundary for gzip and for plaintext' => function () {
        $limit = 50000;
        $exact = str_repeat('a', $limit);
        $over = $exact . 'a';
        $GLOBALS['httpMaxResponseLength'] = $limit;
        $client = new SnoopyOverSocketPair(array(
            "HTTP/1.1 200 OK\r\n\r\n" . $exact,
            "HTTP/1.1 200 OK\r\n\r\n" . $over,
            snoopyGzipResponse(gzencode($exact, 9)),
            snoopyGzipResponse(gzencode($over, 9)),
        ));
        unset($GLOBALS['httpMaxResponseLength']);
        try {
            foreach (array('plaintext' => 'oversized-response-body', 'gzip' => 'invalid-or-oversized-gzip') as $encoding => $reason) {
                snoopyAssertTrue($client->fetch('http://tracker.test/exact'), 'A ' . $encoding . ' body of exactly the bound was refused: ' . $client->error);
                snoopyAssertSame($exact, $client->results, 'A ' . $encoding . ' body of exactly the bound must come back whole');
                snoopyAssertSame(false, $client->fetch('http://tracker.test/over'), 'A ' . $encoding . ' body one byte past the bound was accepted');
                snoopyAssertSame(Snoopy::RESPONSE_BODY_FAILED, $client->status, 'An oversized ' . $encoding . ' body must carry the refusal status');
                snoopyAssertSame($reason, $client->error, 'An oversized ' . $encoding . ' body must say why it was refused');
                snoopyAssertSame('', $client->results, 'An oversized ' . $encoding . ' body must not be handed out in part');
            }
        } finally {
            $client->closeSockets();
        }
    },
    'a lowered bound is the same boundary on the curl path' => function () use ($bodyPath) {
        $limit = 50000;
        $exact = str_repeat('a', $limit);
        putenv('SNOOPY_TEST_BODY=' . $bodyPath);
        $GLOBALS['httpMaxResponseLength'] = $limit;
        try {
            foreach (array('plaintext' => 'oversized-response-body', 'gzip' => 'invalid-or-oversized-gzip') as $encoding => $reason) {
                $gzipped = $encoding === 'gzip';
                snoopyRespondWith($gzipped ? 'HTTP/1.1 200 OK\r\nContent-Encoding: gzip\r\n' : 'HTTP/1.1 200 OK\r\n');
                file_put_contents($bodyPath, $gzipped ? gzencode($exact, 9) : $exact);
                $client = new Snoopy();
                snoopyAssertTrue($client->fetch('https://tracker.test/exact'), 'A ' . $encoding . ' body of exactly the bound was refused on the curl path: ' . $client->error);
                snoopyAssertSame($exact, $client->results, 'A ' . $encoding . ' body of exactly the bound must come back whole on the curl path');
                file_put_contents($bodyPath, $gzipped ? gzencode($exact . 'a', 9) : $exact . 'a');
                $client = new Snoopy();
                snoopyAssertSame(false, $client->fetch('https://tracker.test/over'), 'A ' . $encoding . ' body one byte past the bound was accepted on the curl path');
                snoopyAssertSame(Snoopy::RESPONSE_BODY_FAILED, $client->status, 'An oversized ' . $encoding . ' body must carry the refusal status on the curl path');
                snoopyAssertSame($reason, $client->error, 'An oversized ' . $encoding . ' body must say why it was refused on the curl path');
                snoopyAssertSame('', $client->results, 'An oversized ' . $encoding . ' body must not be handed out in part on the curl path');
            }
        } finally {
            unset($GLOBALS['httpMaxResponseLength']);
            putenv('SNOOPY_TEST_BODY');
            putenv('SNOOPY_TEST_RESPONSE');
        }
    },
    // curl writes the whole answer to a file of its own, and reading all of
    // that file back is how an answer far past the bound ends the request with
    // a memory_limit fatal error before anything can refuse it. One byte past
    // the bound is all that is read, so the refusal fits in the memory the
    // bound implies: a child holding 32 MiB refuses a 64 MiB answer.
    'the curl path refuses a huge answer without reading it into memory' => function () use ($curlPath) {
        $huge = tempnam(sys_get_temp_dir(), 'snoopy-huge-');
        $child = tempnam(sys_get_temp_dir(), 'snoopy-child-');
        $handle = fopen($huge, 'w');
        ftruncate($handle, 64 * 1048576);
        fclose($handle);
        file_put_contents($child, <<<'PHP'
<?php
require_once($argv[1]);
$pathToExternals['curl'] = $argv[2];
$client = new Snoopy();
$client->block_private = false;
$client->maxlength = 1048576;
$fetched = $client->fetch('https://tracker.test/huge');
echo json_encode(array(
    'fetched' => $fetched,
    'status' => $client->status,
    'error' => $client->error,
    'length' => is_string($client->results) ? strlen($client->results) : null,
));
PHP
        );
        putenv('SNOOPY_TEST_BODY=' . $huge);
        try {
            $process = proc_open(
                array(PHP_BINARY, '-c', SnoopyGzipServer::SUITE_INI, '-d', 'memory_limit=32M',
                    $child, __DIR__ . '/../../php/Snoopy.class.inc', $curlPath),
                array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
                $pipes
            );
            snoopyAssertTrue(is_resource($process), 'Could not start the fetching child');
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exit = proc_close($process);
            $seen = json_decode($stdout, true);
            snoopyAssertTrue(
                is_array($seen),
                'The child exited ' . $exit . ' without answering, which is what reading the whole file does; it printed: '
                . trim($stdout . "\n" . $stderr)
            );
            snoopyAssertSame(false, $seen['fetched'], 'A 64 MiB answer was reported as fetched');
            snoopyAssertSame(Snoopy::RESPONSE_BODY_FAILED, $seen['status'], 'The refusal must carry its own status');
            snoopyAssertSame('oversized-response-body', $seen['error'], 'The refusal must say why');
            snoopyAssertSame(0, $seen['length'], 'A refused body must not be handed out in part');
        } finally {
            putenv('SNOOPY_TEST_BODY');
            putenv('SNOOPY_TEST_RESPONSE');
            @unlink($huge);
            @unlink($child);
        }
    },
    // The bound covers the bytes on the wire too, so a body already longer
    // than it is refused without being inflated at all.
    'a gzip body already past the bound on the wire is refused before inflating' => function () {
        $GLOBALS['httpMaxResponseLength'] = 50000;
        $client = new SnoopyOverSocketPair(array(snoopyGzipResponse(gzencode(str_repeat('a', 80000), 0))));
        unset($GLOBALS['httpMaxResponseLength']);
        try {
            snoopyAssertSame(false, $client->fetch('http://tracker.test/stored'), 'A body oversized on the wire was accepted');
            snoopyAssertSame(Snoopy::RESPONSE_BODY_FAILED, $client->status, 'The refusal must carry its own status');
            snoopyAssertSame('oversized-response-body', $client->error, 'The refusal must say it was the size, not the gzip');
            snoopyAssertSame('', $client->results, 'A refused body must not be handed out in part');
        } finally {
            $client->closeSockets();
        }
    },
);

$failures = 0;
foreach ($tests as $name => $callback) {
    try {
        snoopyRespondWith('HTTP/1.1 200 OK\r\n');
        $callback();
        echo "ok - {$name}\n";
    } catch (Throwable $error) {
        $failures++;
        echo "not ok - {$name}\n";
        echo '  ' . get_class($error) . ': ' . $error->getMessage() . "\n";
    }
}
echo count($tests) . ' tests, ' . $failures . " failures\n";

putenv('SNOOPY_TEST_ARGS');
putenv('SNOOPY_TEST_SEEN');
@unlink($seenPath);
@unlink($curlPath);
@unlink($argsPath);
@unlink($bodyPath);
exit($failures === 0 ? 0 : 1);
