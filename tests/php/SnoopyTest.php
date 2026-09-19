<?php

// Deliberately not using tests/plugins/rutracker_check/TestLib.php here:
// Snoopy.class.inc transitively loads php/settings.php -> php/xmlrpc.php,
// whose real rXMLRPC* classes collide with TestLib's doubles in either
// require order. A minimal local runner keeps the real classes intact.
require_once(__DIR__ . '/../../php/Snoopy.class.inc');

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
$curlPath = tempnam(sys_get_temp_dir(), 'snoopy-curl-');
$argsPath = tempnam(sys_get_temp_dir(), 'snoopy-args-');
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
	esac
	shift
done
if [ -n "$SNOOPY_TEST_REDIRECT" ] && [ ! -f "$SNOOPY_TEST_SEEN" ]; then
	: > "$SNOOPY_TEST_SEEN"
	printf 'HTTP/1.1 302 Found\r\nLocation: %s\r\n\r\n' "$SNOOPY_TEST_REDIRECT" > "$header_file"
else
	printf '%b\r\n' "${SNOOPY_TEST_RESPONSE:-HTTP/1.1 200 OK\r\n}" > "$header_file"
fi
: > "$body_file"
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
exit($failures === 0 ? 0 : 1);
