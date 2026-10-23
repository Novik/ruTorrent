<?php

require_once(__DIR__ . '/../../php/Snoopy.class.inc');

// Pin only test DNS to the local peer; the production connector and HTTP writer run.
class RequestStateClient extends Snoopy
{
    public $connections = 0;
    public $throwOnConnect = null;

    public function connect()
    {
        ++$this->connections;
        if ($this->connections === $this->throwOnConnect)
            throw new RuntimeException('Controlled connector exception');
        $this->_pinnedaddr = '127.0.0.1';
        return parent::connect();
    }
}

function stateAssert($condition, $message)
{
    if (!$condition) throw new RuntimeException($message);
}

function stateHeader($wire, $name)
{
    preg_match_all('/^' . preg_quote($name, '/') . ':\s*([^\r\n]*)/mi', $wire, $matches);
    return $matches[1];
}

// A port nothing listens on: reserved, read and released, so a connection to
// it is refused rather than timing out. A case using it asserts that the
// connection failed, so a port taken in between is a loud failure and not a
// case that quietly stops testing what it was written for.
function stateClosedPort()
{
    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    stateAssert($server !== false, 'A port can be reserved to find a closed one');
    $port = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);
    fclose($server);
    return $port;
}

function statePeer(array $responses, callable $body)
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
    if (stream_select($read, $write, $except, 5) !== 1) exit(2);
    if (in_array(STDIN, $read, true)) break;
    $peer = stream_socket_accept($server, 0);
    if (!$peer) exit(3);
    stream_set_timeout($peer, 3);
    $request = '';
    while (($line = fgets($peer)) !== false) {
        $request .= $line;
        if ($line === "\r\n") break;
    }
    $requests[] = $request;
    if (!$responses) exit(4);
    $response = array_shift($responses);
    if (isset($response['delay'])) usleep($response['delay']);
    @fwrite($peer, str_replace('{PORT}', $port, $response['wire']));
    fclose($peer);
}
fclose($server);
echo json_encode($requests), "\n";
PHP;
    $process = proc_open(array(PHP_BINARY, '-c', __DIR__ . '/../php-test.ini', '-r', $worker,
        base64_encode(json_encode($responses))),
        array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
    stateAssert(is_resource($process), 'The local HTTP peer starts');
    try {
        stream_set_timeout($pipes[1], 5);
        $address = fgets($pipes[1]);
        stateAssert(is_string($address) && preg_match('/^127\.0\.0\.1:([0-9]+)\s*$/D', $address, $match) === 1,
            'The peer announces its local port');
        $client = new RequestStateClient();
        $client->proxy_host = $client->proxy_port = '';
        $client->proxy_proto = 'http';
        $client->block_private = false;
        $client->read_timeout = 1;
        $client->_fp_timeout = 2;
        $check = $body($client, (int) $match[1]);
        fwrite($pipes[0], "done\n");
        fclose($pipes[0]);
        $requests = json_decode(stream_get_contents($pipes[1]), true);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $exit = proc_close($process); $process = null;
        stateAssert($exit === 0 && $errors === '' && is_array($requests),
            'The peer finishes successfully: ' . $exit . ' ' . $errors);
        $check($requests);
    } finally {
        if (is_resource($process)) {
            proc_terminate($process);
            foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
            proc_close($process);
        }
    }
}

$ok = array('wire' => "HTTP/1.0 200 OK\r\n\r\nok");
$redirect = array('wire' => "HTTP/1.0 302 Found\r\nLocation: http://first.test:{PORT}/next\r\n\r\n");
$tests = array(
    'URL credentials do not reach a later explicit host' => function () use ($ok) {
        statePeer(array($ok, $ok), function ($client, $port) {
            stateAssert($client->fetch('http://url-user:url-pass@first.test:' . $port . '/start'), 'The URL-authenticated request succeeds');
            stateAssert($client->fetch('http://second.test:' . $port . '/file'), 'The independent request succeeds');
            return function ($requests) {
                stateAssert(count($requests) === 2, 'Both explicit requests reach the peer');
                stateAssert(stateHeader($requests[0], 'Authorization') === array('Basic ' . base64_encode('url-user:url-pass')), 'URL credentials reach their intended request');
                stateAssert(stateHeader($requests[1], 'Authorization') === array(), 'A different host receives no prior URL credentials');
                stateAssert(strpos($requests[1], 'Host: second.test:') !== false, 'The second authority is distinct');
            };
        });
    },
    'URL credentials restore caller configured credentials' => function () use ($redirect, $ok) {
        statePeer(array($redirect, $ok, $ok), function ($client, $port) {
            $client->user = 'configured-user'; $client->pass = 'configured-pass';
            stateAssert($client->fetch('http://temporary:secret@first.test:' . $port . '/start'), 'The temporary request succeeds');
            stateAssert($client->user === 'configured-user' && $client->pass === 'configured-pass', 'Caller credentials are restored');
            stateAssert($client->fetch('http://second.test:' . $port . '/file'), 'The later configured request succeeds');
            return function ($requests) {
                stateAssert(count($requests) === 3, 'The authenticated chain and later request arrive');
                stateAssert(stateHeader($requests[0], 'Authorization') === array('Basic ' . base64_encode('temporary:secret')), 'URL credentials override only their own request');
                stateAssert(stateHeader($requests[1], 'Authorization') === array('Basic ' . base64_encode('temporary:secret')), 'The same-origin redirect keeps its chain credentials');
                stateAssert(stateHeader($requests[2], 'Authorization') === array('Basic ' . base64_encode('configured-user:configured-pass')), 'The caller configuration remains usable');
            };
        });
    },
    'a username-only URL does not borrow a configured password' => function () use ($ok) {
        statePeer(array($ok), function ($client, $port) {
            $client->user = 'configured-user'; $client->pass = 'configured-pass';
            stateAssert($client->fetch('http://temporary@first.test:' . $port . '/start'), 'The username-only request succeeds');
            stateAssert($client->pass === 'configured-pass', 'The configured password is retained for its caller');
            return function ($requests) {
                stateAssert(stateHeader($requests[0], 'Authorization') === array('Basic ' . base64_encode('temporary:')), 'Absent URL password is empty on the wire');
            };
        });
    },
    'empty URL userinfo is scoped without erasing caller credentials' => function () use ($ok) {
        statePeer(array($ok), function ($client, $port) {
            $client->user = 'configured-user'; $client->pass = 'configured-pass';
            stateAssert($client->fetch('http://@first.test:' . $port . '/start'), 'The explicitly anonymous request succeeds');
            stateAssert($client->user === 'configured-user' && $client->pass === 'configured-pass', 'Caller credentials survive empty userinfo');
            return function ($requests) {
                stateAssert(stateHeader($requests[0], 'Authorization') === array(), 'Empty URL credentials do not inherit caller authentication');
            };
        });
    },
    'a failed URL-authenticated request restores caller credentials' => function () {
        statePeer(array(array('wire' => "HTTP/1.0 200 OK\r\n\r\nlate", 'delay' => 1300000)), function ($client, $port) {
            $client->user = 'configured-user'; $client->pass = 'configured-pass';
            stateAssert($client->fetch('http://temporary:secret@first.test:' . $port . '/start') === false && $client->status === -100, 'The real delayed peer causes a read timeout');
            stateAssert($client->user === 'configured-user' && $client->pass === 'configured-pass', 'Failure still restores caller credentials');
            return function ($requests) { stateAssert(count($requests) === 1, 'The timed-out request reached the peer'); };
        });
    },
    'exceptions unwind both URL credentials and redirect depth' => function () use ($redirect, $ok) {
        statePeer(array($redirect, $ok), function ($client, $port) {
            $client->user = 'configured-user'; $client->pass = 'configured-pass';
            $client->throwOnConnect = 2;
            $caught = false;
            try { $client->fetch('http://temporary:secret@first.test:' . $port . '/start'); }
            catch (RuntimeException $error) { $caught = $error->getMessage() === 'Controlled connector exception'; }
            stateAssert($caught, 'The original connector exception propagates');
            stateAssert($client->_redirectdepth === 0, 'A failed nested connection releases its recursion depth');
            stateAssert($client->user === 'configured-user' && $client->pass === 'configured-pass', 'The exceptional chain restores caller credentials');
            $client->throwOnConnect = null;
            stateAssert($client->fetch('http://first.test:' . $port . '/recovery'), 'The same client remains usable');
            return function ($requests) { stateAssert(count($requests) === 2, 'Only the first and recovery requests reached the peer'); };
        });
    },
    'each explicit request has its own redirect allowance' => function () use ($redirect, $ok) {
        statePeer(array($redirect, $ok, $redirect, $ok), function ($client, $port) {
            $client->maxredirs = 1;
            for ($attempt = 0; $attempt < 2; ++$attempt) {
                stateAssert($client->fetch('http://first.test:' . $port . '/start') && (int) $client->status === 200, 'Each chain reaches its final response');
            }
            stateAssert($client->_redirectdepth === 0, 'Each finished chain releases its recursion depth');
            return function ($requests) { stateAssert(count($requests) === 4, 'Both explicit requests followed one redirect'); };
        });
    },
    'a capped chain stays bounded and its last redirect does not leak' => function () use ($redirect, $ok) {
        statePeer(array($redirect, $redirect, $ok), function ($client, $port) {
            $client->maxredirs = 1;
            stateAssert($client->fetch('http://first.test:' . $port . '/start') && (int) $client->status === 302, 'The second redirect remains capped');
            stateAssert($client->_redirectdepth === 0, 'The capped chain releases its recursion depth');
            stateAssert($client->lastredirectaddr !== '', 'The chain reports its followed redirect');
            stateAssert($client->fetch('http://first.test:' . $port . '/plain'), 'The next plain request succeeds');
            stateAssert($client->lastredirectaddr === '', 'The previous redirect is not reported for this request');
            return function ($requests) { stateAssert(count($requests) === 3, 'The cap allows two requests, then the independent request'); };
        });
    },
    'configured credentials still drop on a foreign redirect' => function () use ($ok) {
        $foreign = array('wire' => "HTTP/1.0 302 Found\r\nLocation: http://second.test:{PORT}/file\r\n\r\n");
        statePeer(array($foreign, $ok), function ($client, $port) {
            $client->user = 'configured-user'; $client->pass = 'configured-pass';
            stateAssert($client->fetch('http://first.test:' . $port . '/start'), 'The foreign redirect completes');
            stateAssert($client->user === '' && $client->pass === '', 'Existing foreign-origin credential removal is retained');
            return function ($requests) {
                stateAssert(count($requests) === 2, 'The anonymous foreign hop is followed');
                stateAssert(stateHeader($requests[0], 'Authorization') === array('Basic ' . base64_encode('configured-user:configured-pass')), 'The source receives configured authentication');
                stateAssert(stateHeader($requests[1], 'Authorization') === array(), 'The foreign authority receives no configured credentials');
            };
        });
    },
    'URL userinfo keeps its precedence after HostConnect' => function () use ($ok) {
        $name = 'request_state_' . bin2hex(random_bytes(6));
        $temporary = sys_get_temp_dir() . '/' . $name;
        $plugin = __DIR__ . '/../../plugins/' . $name;
        stateAssert(mkdir($temporary, 0700), 'The private hook fixture directory opens');
        $settings = rTorrentSettings::get();
        $previous = $settings->hooks;
        $linked = false;
        try {
            $source = '<?php class ' . $name . 'Hooks { static function OnHostConnect($prm) {'
                . '$client = $prm["client"];'
                . '$GLOBALS["requestStateHookSaw"][] = array($client->user, $client->pass);'
                . '$client->user = "hook-user"; $client->pass = "hook-pass"; return false; } }';
            stateAssert(file_put_contents($temporary . '/hooks.php', $source) === strlen($source), 'The hook fixture is complete');
            stateAssert(symlink($temporary, $plugin), 'The dispatcher can reach the private fixture');
            $linked = true;
            $settings->hooks['HostConnect'] = array(array('name' => $name, 'level' => 10));
            $GLOBALS['requestStateHookSaw'] = array();
            statePeer(array($ok), function ($client, $port) {
                $client->user = 'configured-user'; $client->pass = 'configured-pass';
                stateAssert($client->fetch('http://url-user:url-pass@first.test:' . $port . '/start'), 'The hook-enabled request succeeds');
                $seen = $GLOBALS['requestStateHookSaw'];
                return function ($requests) use ($seen) {
                    stateAssert($seen === array(array('configured-user', 'configured-pass')), 'HostConnect observes caller configuration before the URL override');
                    stateAssert(stateHeader($requests[0], 'Authorization') === array('Basic ' . base64_encode('url-user:url-pass')), 'Explicit URL credentials retain precedence over a hook default');
                };
            });
        } finally {
            $settings->hooks = $previous;
            unset($GLOBALS['requestStateHookSaw']);
            if ($linked) unlink($plugin);
            @unlink($temporary . '/hooks.php');
            rmdir($temporary);
        }
    },
    // A request the client refuses before it sends anything still has to
    // answer for itself. Callers reuse one client for a sequence of fetches
    // and read $status and $results rather than the return value, so a refusal
    // that leaves those alone hands back the last response that worked.
    'a refused request discards the previous response' => function () use ($ok) {
        statePeer(array($ok, $ok), function ($client, $port) {
            $client->private_allowlist = array();
            $refusals = array(
                'a blocked private target' => array('url' => 'http://127.0.0.1:' . $port . '/blocked', 'block' => true),
                'an unsupported protocol' => array('url' => 'ftp://first.test:' . $port . '/file', 'block' => false),
            );
            foreach ($refusals as $name => $case) {
                $client->block_private = false;
                stateAssert($client->fetch('http://first.test:' . $port . '/ok'), 'The seed request succeeds before ' . $name);
                stateAssert($client->results === 'ok' && (int) $client->status === 200 && $client->headers !== array(),
                    'The seed response is a complete success before ' . $name);
                $connections = $client->connections;
                $client->block_private = $case['block'];
                stateAssert($client->fetch($case['url']) === false, $name . ' is refused');
                stateAssert($client->connections === $connections, $name . ' opens no connection');
                stateAssert($client->status === Snoopy::RESPONSE_BODY_FAILED, $name . ' replaces the earlier status');
                stateAssert($client->results === '', $name . ' discards the earlier body');
                stateAssert($client->headers === array() && $client->response_code === '',
                    $name . ' discards the earlier headers');
                stateAssert($client->_redirectaddr === false, $name . ' discards the earlier redirect');
                stateAssert($client->error !== '', $name . ' states its own reason');
            }
            stateAssert(strpos($client->error, 'invalid-protocol') === 0 && strpos($client->error, 'ftp') !== false,
                'The refused protocol stays readable in the reason');
            return function ($requests) {
                stateAssert(count($requests) === 2, 'Only the two seed requests reach the peer');
            };
        });
    },
    // A request whose socket never opened is in the same position as one the
    // client refused before sending it: nothing of it answers for the request,
    // and a caller reusing one client reads $results and $headers rather than
    // the return value.
    'a failed connection discards the previous response' => function () use ($ok) {
        $closed = stateClosedPort();
        statePeer(array($ok), function ($client, $port) use ($closed) {
            stateAssert($client->fetch('http://first.test:' . $port . '/ok'), 'The seed request succeeds');
            stateAssert($client->results === 'ok' && (int) $client->status === 200
                && $client->headers !== array() && $client->response_code !== '',
                'The seed response is a complete success');
            stateAssert(@$client->fetch('http://first.test:' . $closed . '/gone') === false,
                'The closed port refuses the connection');
            stateAssert($client->status === Snoopy::SOCKET_CONNECT_FAILED,
                'A failed connection reports the transport status');
            stateAssert($client->results === '', 'A failed connection discards the earlier body');
            stateAssert($client->headers === array() && $client->response_code === '',
                'A failed connection discards the earlier headers');
            stateAssert($client->_redirectaddr === false,
                'A failed connection discards the earlier redirect');
            return function ($requests) {
                stateAssert(count($requests) === 1, 'Only the seed request reaches the peer');
            };
        });
    },
    // $passcookies carries a cookie through a redirect the client follows.
    // Nothing else may carry one: a chain that stops at its depth cap leaves a
    // redirect target behind, and the next request a caller makes -- to any
    // host, with no cookie handling of its own -- must not inherit it.
    'an independent request sends no cookie from the previous response' => function () use ($ok) {
        $capped = array('wire' => "HTTP/1.0 302 Found\r\nSet-Cookie: leaked=yes; path=/\r\n"
            . "Location: http://first.test:{PORT}/next\r\n\r\n");
        statePeer(array($capped, $ok), function ($client, $port) {
            $client->maxredirs = 0;
            stateAssert($client->fetch('http://first.test:' . $port . '/start') && (int) $client->status === 302,
                'The capped redirect is answered but not followed');
            stateAssert($client->fetch('http://second.test:' . $port . '/file') && $client->results === 'ok',
                'The independent request succeeds');
            stateAssert($client->cookies === array(), 'The independent request absorbs no cookie of its own');
            return function ($requests) {
                stateAssert(count($requests) === 2, 'Both requests reach the peer');
                stateAssert(stateHeader($requests[0], 'Cookie') === array(), 'The first request carries no cookie');
                stateAssert(stateHeader($requests[1], 'Cookie') === array(),
                    'A different host receives no cookie set by the previous response');
            };
        });
    },
    // The cookie a redirect sets reaches the hop that redirect names, which
    // is the one thing $passcookies is for.
    'a cookie set on a followed redirect reaches the next hop' => function () use ($ok) {
        $handover = array('wire' => "HTTP/1.0 302 Found\r\nSet-Cookie: session=abc; path=/\r\n"
            . "Location: http://first.test:{PORT}/next\r\n\r\n");
        statePeer(array($handover, $ok), function ($client, $port) {
            stateAssert($client->fetch('http://first.test:' . $port . '/start') && $client->results === 'ok',
                'The redirect is followed to its answer');
            return function ($requests) {
                stateAssert(count($requests) === 2, 'The chain is two requests long');
                stateAssert(stateHeader($requests[1], 'Cookie') === array('session=abc'),
                    'The followed hop carries the cookie its redirect set');
            };
        });
    },
    // A reply whose body the client will not take is not a response. Nothing
    // of it answers for the request it arrived on, and nothing of it is left
    // behind for a caller's own setcookies() to read.
    'a reply the client refuses leaves nothing of itself' => function () use ($ok) {
        $undecodable = array('wire' => "HTTP/1.0 302 Found\r\nSet-Cookie: stale=yes; path=/\r\n"
            . "Location: http://first.test:{PORT}/next\r\nContent-Encoding: gzip\r\n\r\nnotgzipdata-notgzipdata");
        statePeer(array($ok, $undecodable, $ok), function ($client, $port) {
            stateAssert($client->fetch('http://first.test:' . $port . '/ok') && $client->results === 'ok',
                'The seed request succeeds');
            stateAssert($client->fetch('http://first.test:' . $port . '/start') === false
                && $client->status === Snoopy::RESPONSE_BODY_FAILED, 'The undecodable reply fails the request');
            stateAssert($client->results === '' && $client->headers === array() && $client->response_code === '',
                'The refused reply reports no response');
            stateAssert($client->_redirectaddr === false, 'The refused reply reports no redirect');
            $client->setcookies();
            stateAssert($client->cookies === array(), 'A refused reply is no source of cookies');
            stateAssert($client->fetch('http://second.test:' . $port . '/file') && $client->results === 'ok',
                'The next independent request succeeds');
            return function ($requests) {
                stateAssert(count($requests) === 3, 'All three requests reach the peer');
                stateAssert(stateHeader($requests[2], 'Cookie') === array(),
                    'No cookie from the refused reply reaches the wire');
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
