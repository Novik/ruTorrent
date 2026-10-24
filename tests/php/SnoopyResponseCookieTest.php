<?php

// Synthetic responses exercise the public fetch path and both request writers.
// The final case also uses a real loopback HTTP server. The curl double checks
// argv and response ownership, not TLS or certificate verification.
require_once(__DIR__ . '/TestTree.php');
$jarConfigurationChanged = false;
$jarRoot = sys_get_temp_dir() . '/response-cookies-' . getmypid();
try {
if (!mkdir($jarRoot . '/profile/settings', 0700, true)) throw new RuntimeException('Could not create private cookie fixture tree');
$_ENV['RU_PROFILE_PATH'] = $jarRoot . '/profile';
$_ENV['RU_LOG_FILE'] = $jarRoot . '/application.log';
require_once(__DIR__ . '/../../php/Snoopy.class.inc');
require_once(__DIR__ . '/TestHttpServer.php');

function jarSame($expected, $actual, $message)
{
    if ($expected !== $actual) throw new RuntimeException($message . '; expected ' . var_export($expected, true)
        . ', got ' . var_export($actual, true));
}

class ResponseCookieSocket extends Snoopy
{
    public $plan = array();
    private $peers = array();
    public $requests = array();

    public function connect()
    {
        $reply = array_shift($this->plan);
        if ($reply === false) { $this->discardResponse(); return false; }
        if (!is_string($reply)) throw new RuntimeException('Unexpected socket request');
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        if ($pair === false) throw new RuntimeException('Could not create cookie test peer');
        if (fwrite($pair[1], $reply) !== strlen($reply)) {
            fclose($pair[0]); fclose($pair[1]);
            throw new RuntimeException('Could not write cookie test response');
        }
        stream_socket_shutdown($pair[1], STREAM_SHUT_WR);
        $this->peers[] = $pair[1];
        return $pair[0];
    }

    public function finish()
    {
        foreach ($this->peers as $peer) {
            stream_set_blocking($peer, false);
            $this->requests[] = stream_get_contents($peer);
            fclose($peer);
        }
        $this->peers = array();
    }
}

class ResponseCookieLoopback extends Snoopy
{
    public function connect()
    {
        $this->_pinnedaddr = '127.0.0.1';
        return parent::connect();
    }
}

function jarReply($cookies = array(), $extra = '')
{
    return "HTTP/1.1 200 OK\r\n" . implode('', array_map(function ($cookie) {
        return 'Set-Cookie: ' . $cookie . "\r\n";
    }, $cookies)) . $extra . "\r\n";
}

function jarClient($transport, $plan)
{
    global $jarRoot, $jarClients;
    if ($transport === 'socket') {
        $client = new ResponseCookieSocket();
        $client->plan = $plan;
        $jarClients[] = $client;
    } else {
        file_put_contents($jarRoot . '/plan', serialize($plan));
        file_put_contents($jarRoot . '/requests', serialize(array()));
        $client = new Snoopy();
    }
    $client->block_private = false;
    $client->proxy_host = $client->proxy_port = '';
    return $client;
}

function jarRequests($client)
{
    global $jarRoot;
    if ($client instanceof ResponseCookieSocket) {
        $client->finish();
        return $client->requests;
    }
    return unserialize(file_get_contents($jarRoot . '/requests'), array('allowed_classes' => false));
}

function jarFields($wire)
{
    preg_match_all('/^Cookie:[ \t]*(.*?)\r?$/mi', $wire, $matches);
    return $matches[1];
}

// A cookie lifetime is counted in whole seconds, so a tuple received at X.95
// with Max-Age=1 dies 0.05s later: the window a case gets is a random slice of
// one second, not a second. A case that must still hold a live tuple after
// doing work starts just after a tick, so the whole lifetime is its own.
function jarTick()
{
    $second = time();
    while (time() === $second) usleep(5000);
}

function jarRoundTrip($transport, $cookies, $source, $target, $flat = array(), $explicit = false)
{
    $client = jarClient($transport, array(jarReply($cookies), jarReply()));
    $client->cookies = $flat;
    jarSame(true, $client->fetch($source), 'The source response completes');
    if ($explicit) $client->setcookies();
    jarSame(true, $client->fetch($target), 'The target request completes');
    return jarFields(jarRequests($client)[1]);
}

$oldCurl = $pathToExternals['curl'] ?? null;
$oldRoot = getenv('SNOOPY_COOKIE_ROOT');
$jarConfigurationChanged = true;
putenv('SNOOPY_COOKIE_ROOT=' . $jarRoot);
$curlFixture = <<<'CURL'
<?php
$root = getenv('SNOOPY_COOKIE_ROOT');
$headers = array();
for ($i = 1; $i < $argc - 1; $i++) {
    if ($argv[$i] === '-D') $header = $argv[++$i];
    elseif ($argv[$i] === '-o') $body = $argv[++$i];
    elseif ($argv[$i] === '-H') $headers[] = $argv[++$i];
}
$requests = unserialize(file_get_contents($root . '/requests'), array('allowed_classes' => false));
$requests[] = implode("\r\n", $headers) . "\r\n";
file_put_contents($root . '/requests', serialize($requests));
$plan = unserialize(file_get_contents($root . '/plan'), array('allowed_classes' => false));
$reply = array_shift($plan);
file_put_contents($root . '/plan', serialize($plan));
if ($reply === false) exit(7);
if (!is_string($reply)) exit(99);
$parts = explode("\r\n\r\n", $reply, 2);
file_put_contents($header, $parts[0] . "\r\n\r\n");
file_put_contents($body, $parts[1] ?? '');
CURL;
if (@file_put_contents($jarRoot . '/curl.php', $curlFixture) !== strlen($curlFixture)) throw new RuntimeException('Could not write curl fixture');
$curlWrapper = "#!/bin/sh\nexec " . escapeshellarg(PHP_BINARY)
    . ' -c ' . escapeshellarg(__DIR__ . '/../php-test.ini') . ' ' . escapeshellarg($jarRoot . '/curl.php') . ' "$@"' . "\n";
if (@file_put_contents($jarRoot . '/curl', $curlWrapper) !== strlen($curlWrapper)) throw new RuntimeException('Could not write curl fixture wrapper');
if (!@chmod($jarRoot . '/curl', 0700)) throw new RuntimeException('Could not protect curl fixture wrapper');
$pathToExternals['curl'] = $jarRoot . '/curl';
$tests = array();
foreach (array('socket' => 'http', 'curl' => 'https') as $transport => $scheme) {
    $origin = $scheme . '://tracker.example';
    $tests[$transport . ' real adapter cache retains a live tuple after unrelated deletion in either order'] = function () use ($transport, $scheme, $origin) {
        require_once(__DIR__ . '/../../plugins/loginmgr/accounts.php');
        require_once(__DIR__ . '/../../plugins/loginmgr/accounts/RUTracker.php');
        $variants = array(
            'path' => array($origin, array('sid=session; Path=/'), 'sid=; Path=/legacy; Max-Age=0'),
            'manual-root' => array($origin, array('sid=session; Path=/'), 'sid=; Path=/legacy; Max-Age=0'),
            'replacement' => array($origin, array('sid=old; Path=/', 'sid=session; Path=/'), 'sid=; Path=/legacy; Max-Age=0'),
            'host-only' => array($origin, array('sid=session; Path=/'), 'sid=; Domain=tracker.example; Path=/; Max-Age=0'),
            'child-host' => array($scheme . '://a.example.com', array('sid=session; Path=/'), 'sid=; Domain=example.com; Path=/; Max-Age=0'),
            'equal-path-values' => array($origin, array('sid=session; Path=/legacy'), 'sid=; Path=/; Max-Age=0')
        );
        foreach ($variants as $kind => $variant) foreach (array('set-delete', 'delete-set', 'prior-response') as $order) {
            list($url, $accepted, $deleted) = $variant;
            $account = new ruTrackerAccount(); $account->url = $url;
            privateData::load($account->getName())->remove();
            $redirect = function ($cookies) use ($url) {
                return str_replace('200 OK', '302 Found', jarReply($cookies, 'Location: ' . $url . "/legacy/landing\r\n")) . 'live';
            };
            if ($kind === 'equal-path-values') array_unshift($accepted, 'sid=session; Path=/');
            $plan = $order === 'prior-response' ? array($redirect($accepted), $redirect(array($deleted)))
                : array($redirect($order === 'set-delete' ? array_merge($accepted, array($deleted)) : array_merge(array($deleted), $accepted)));
            // The deleted root tuple must not be re-created by the reverse-order control.
            if ($kind === 'equal-path-values' && $order === 'delete-set') $plan = array($redirect(array($deleted, 'sid=session; Path=/legacy')));
            $plan[] = jarReply() . 'live';
            $login = jarClient($transport, $plan);
            if ($kind === 'manual-root') $login->cookies['sid'] = 'session';
            jarSame(true, $account->fetch($login, $url . '/legacy/landing', 'user', 'pass', 'GET', '', ''), 'The real adapter completes: ' . $kind . '/' . $order);
            $wire = jarRequests($login);
            jarSame(array('sid=session'), jarFields($wire[count($wire) - 1]), 'The remaining tuple reaches the final redirect: ' . $kind . '/' . $order);
            $fresh = jarClient($transport, array(jarReply()));
            jarSame(true, privateData::load($account->getName(), $fresh)->loaded, 'The real adapter cache loads');
            $fresh->fetch($url . '/legacy/item');
            jarSame(array('sid=session'), jarFields(jarRequests($fresh)[0]), 'Unrelated tuple deletion preserves the saved session: ' . $kind . '/' . $order);
        }
    };
    $tests[$transport . ' equal manual facade stays manual through unrelated deletion expiry and override'] = function () use ($transport, $origin, $scheme) {
        jarTick();
        $client = jarClient($transport, array(jarReply(array('sid=manual; Path=/private; Max-Age=2')), jarReply(array('sid=; Path=/legacy; Max-Age=0')), jarReply(), jarReply()));
        $client->cookies = array('sid' => 'manual', 'caller' => 'keep');
        $client->fetch($origin . '/private/login'); $client->setcookies();
        $client->fetch($origin . '/private/login'); $client->setcookies();
        jarSame(array('sid' => 'manual', 'caller' => 'keep'), $client->cookies, 'A later unrelated deletion preserves equal manual input');
        sleep(3);
        $client->fetch($scheme . '://other.example/public'); $client->setcookies();
        jarSame(array('sid=manual; caller=keep'), jarFields(jarRequests($client)[2]), 'Explicit manual input survives tuple expiry and remains flat on the wire');
        $client->cookies['sid'] = 'changed';
        $client->fetch($origin . '/public');
        jarSame(array('sid=changed; caller=keep'), jarFields(jarRequests($client)[3]), 'A changed manual override remains usable');
        require_once(__DIR__ . '/../../plugins/loginmgr/accounts.php');
        $saved = new privateData('ManualCookieFacade');
        jarSame(true, $saved->store($client), 'The changed manual facade is stored');
        $fresh = jarClient($transport, array(jarReply()));
        jarSame(true, privateData::load('ManualCookieFacade', $fresh)->loaded, 'The changed manual facade is loaded');
        $fresh->fetch($origin . '/public');
        jarSame(array('sid=changed; caller=keep'), jarFields(jarRequests($fresh)[0]), 'The real cache retains a changed manual override');
    };
    $tests[$transport . ' accepted deletion-only actions retain manual and derived retirement controls'] = function () use ($transport, $origin) {
        foreach (array('sid=deleted; Path=/', 'sid=; Path=/; Max-Age=0') as $deleted) {
            $client = jarClient($transport, array(jarReply(array($deleted)), jarReply()));
            $client->cookies = array('sid' => 'manual', 'caller' => 'keep');
            $client->fetch($origin . '/'); $client->setcookies(); $client->fetch($origin . '/');
            jarSame(array('caller=keep'), jarFields(jarRequests($client)[1]), 'An accepted explicit deletion still retires an unbound manual name');
        }
        $client = jarClient($transport, array(jarReply()));
        $client->cookies = array('sid' => 'manual', 'caller' => 'keep');
        $client->headers = array('Set-Cookie: sid=deleted; Path=/legacy'); $client->setcookies();
        $client->fetch($origin . '/');
        jarSame(array('caller=keep'), jarFields(jarRequests($client)[0]), 'Header-only legacy sentinel deletion remains available');
    };
    $tests[$transport . ' equal foreign jar values cannot protect another tuple facade'] = function () use ($transport, $scheme) {
        require_once(__DIR__ . '/../../plugins/loginmgr/accounts.php');
        foreach (array(false, true) as $exportOther) {
            $client = jarClient($transport, array(jarReply(array('sid=shared; Path=/; Max-Age=1')), jarReply(array('sid=shared; Path=/')), jarReply()));
            $client->fetch($scheme . '://a.example.com/'); $client->setcookies();
            $client->fetch($scheme . '://b.other.example/');
            if ($exportOther) $client->setcookies();
            sleep(2);
            $client->fetch($scheme . '://a.example.com/'); $client->setcookies();
            jarRequests($client);
            $saved = new privateData('ExactCookieFacadeTuple');
            jarSame(true, $saved->store($client), 'The exact facade is stored');
            $fresh = jarClient($transport, array(jarReply()));
            jarSame(true, privateData::load('ExactCookieFacadeTuple', $fresh)->loaded, 'The exact facade is loaded');
            $fresh->fetch($scheme . '://b.other.example/');
            jarSame($exportOther ? array('sid=shared') : array(), jarFields(jarRequests($fresh)[0]), 'Only explicit export transfers facade provenance to the other tuple');
        }
    };
    $tests[$transport . ' unrelated response headers cannot hide cookie acquisition or deletion'] = function () use ($transport, $origin) {
        $padding = str_repeat("X-Policy: unrelated\r\n", 110);
        $client = jarClient($transport, array("HTTP/1.1 200 OK\r\n" . $padding . "Set-Cookie: sid=live; Path=/\r\n\r\n", "HTTP/1.1 200 OK\r\n" . $padding . "Set-Cookie: sid=; Max-Age=0; Path=/\r\n\r\n", jarReply()));
        $client->fetch($origin . '/'); $client->setcookies();
        $client->fetch($origin . '/'); $client->setcookies();
        $client->fetch($origin . '/');
        $wire = jarRequests($client);
        jarSame(array('sid=live'), jarFields($wire[1]), 'Unrelated headers do not consume the cookie scan allowance');
        jarSame(array(), jarFields($wire[2]), 'A late deletion still retires its cookie');
    };
    $tests[$transport . ' real adapter cache exports only accepted live response cookies'] = function () use ($transport, $scheme) {
        require_once(__DIR__ . '/../../plugins/loginmgr/accounts.php');
        require_once(__DIR__ . '/../../plugins/loginmgr/accounts/RUTracker.php');
        $variants = array('valid' => 'sid=session; Path=/', 'icann' => 'sid=refused; Domain=co.uk; Path=/',
            'private' => 'sid=refused; Domain=github.io; Path=/', 'foreign' => 'sid=refused; Domain=foreign.example; Path=/',
            'secure-http' => 'sid=refused; Secure; Path=/', 'prefix' => '__Secure-sid=refused; Path=/',
            'expired' => 'sid=refused; Max-Age=0; Path=/', 'oversized' => 'sid=' . str_repeat('x', 4097) . '; Path=/');
        foreach ($variants as $kind => $cookie) {
            $url = ($kind === 'secure-http' ? 'http' : $scheme) . '://a.example.co.uk';
            $account = new ruTrackerAccount(); $account->url = $url;
            privateData::load($account->getName())->remove();
            $source = str_replace('200 OK', '302 Found', jarReply(array($cookie), 'Location: ' . $url . "/landing\r\n")) . 'live';
            $login = jarClient($transport, array($source, jarReply() . 'live'));
            $login->_maxlinelen = 16384;
            if ($transport === 'curl') $login->proxy_proto = 'https';
            jarSame(true, $account->fetch($login, $url . '/landing', 'user', 'pass', 'GET', '', ''), 'The actual login adapter completes: ' . $kind);
            $fresh = jarClient($transport, array(jarReply()));
            if ($transport === 'curl') $fresh->proxy_proto = 'https';
            jarSame(true, privateData::load($account->getName(), $fresh)->loaded, 'The actual adapter cache loads: ' . $kind);
            $fresh->fetch($url . '/next');
            jarSame($kind === 'valid' ? array('sid=session') : array(), jarFields(jarRequests($fresh)[0]), 'Known refused or expired values never enter a fresh legacy cache: ' . $kind);
        }
    };
    $tests[$transport . ' response cookies beyond the scan allowance cannot enter a fresh cache'] = function () use ($transport, $origin) {
        require_once(__DIR__ . '/../../plugins/loginmgr/accounts.php');
        $cookies = array();
        for ($i = 0; $i < 100; $i++) $cookies[] = 'c' . $i . '=v; Path=/';
        $cookies[] = 'sid=unprocessed; Path=/';
        $client = jarClient($transport, array(jarReply($cookies)));
        $client->fetch($origin . '/'); $client->setcookies();
        $saved = new privateData('CookieScanAllowance');
        jarSame(true, $saved->store($client), 'The accepted legacy facade is saved');
        $fresh = jarClient($transport, array(jarReply()));
        privateData::load('CookieScanAllowance', $fresh);
        $fresh->fetch($origin . '/');
        $fields = jarFields(jarRequests($fresh)[0]);
        jarSame(false, strpos($fields[0], 'sid=unprocessed') !== false, 'An unprocessed cookie does not bypass its parser through storage');
        jarSame(50, count(explode('; ', $fields[0])), 'Only the live retained tuples are exported');
    };
    $tests[$transport . ' empty response deletions survive a redirect login cache round trip'] = function () use ($transport, $origin) {
        require_once(__DIR__ . '/../../plugins/loginmgr/accounts.php');
        require_once(__DIR__ . '/../../plugins/loginmgr/accounts/RUTracker.php');
        $account = new ruTrackerAccount(); $account->url = $origin;
        privateData::load($account->getName())->remove();
        $login = jarClient($transport, array("HTTP/1.1 302 Found\r\nSet-Cookie: sid=old; Path=/\r\nLocation: " . $origin . "/clear\r\n\r\nlive", "HTTP/1.1 302 Found\r\nSet-Cookie: sid=; Max-Age=0; Path=/\r\nLocation: " . $origin . "/landing\r\n\r\nlive", jarReply() . 'live'));
        jarSame(true, $account->fetch($login, $origin . '/landing', 'user', 'pass', 'GET', '', ''), 'The adapter completes after a deletion redirect');
        $fresh = jarClient($transport, array(jarReply()));
        privateData::load($account->getName(), $fresh);
        $fresh->fetch($origin . '/next');
        jarSame(array(), jarFields(jarRequests($fresh)[0]), 'A deleted value is absent from the saved facade');
    };
    $tests[$transport . ' captures terminal response cookies without flattening caller state'] = function () use ($transport, $origin) {
        $client = jarClient($transport, array(jarReply(array('sid=response; Path=/')), jarReply()));
        $client->cookies = array('manual' => 'yes');
        jarSame(true, $client->fetch($origin . '/start'), 'The source completes');
        jarSame(array('manual' => 'yes'), $client->cookies, 'Automatic capture leaves the public facade intact');
        jarSame(true, $client->fetch($origin . '/next'), 'An explicit reused fetch completes');
        jarSame(array('manual=yes; sid=response'), jarFields(jarRequests($client)[1]), 'Manual and response cookies share one field');
    };
    $tests[$transport . ' keeps host-only cookies off sibling and unrelated hosts'] = function () use ($transport, $origin, $scheme) {
        foreach (array('sub.tracker.example', 'other.example', 'tracker.example.evil.test') as $host)
            jarSame(array(), jarRoundTrip($transport, array('sid=private; Path=/'), $origin . '/', $scheme . '://' . $host . '/'), 'A host-only cookie stays on its source host');
        jarSame(array('sid=private'), jarRoundTrip($transport, array('sid=private'), $origin . '/', $scheme . '://TRACKER.EXAMPLE./'), 'DNS case and root dot normalize');
    };
    $tests[$transport . ' accepts matching Domain and refuses widening and public suffixes'] = function () use ($transport, $scheme) {
        jarSame(array('sid=shared'), jarRoundTrip($transport, array('sid=shared; Domain=.example.co.uk; Path=/'), $scheme . '://a.example.co.uk/', $scheme . '://b.example.co.uk/'), 'A registrable parent Domain admits a sibling');
        foreach (array('co.uk', 'github.io', 'a.ck', 'other.example', 'example.co.uk.', '', '127.0.0.1', 'xn--tst-qla.example') as $domain)
            jarSame(array(), jarRoundTrip($transport, array('sid=bad; Domain=' . $domain), $scheme . '://a.example.co.uk/', $scheme . '://a.example.co.uk/'), 'An invalid Domain is refused: ' . $domain);
        jarSame(array('sid=ok'), jarRoundTrip($transport, array('sid=ok; Domain=www.ck'), $scheme . '://a.www.ck/', $scheme . '://b.www.ck/'), 'A PSL exception remains registrable');
    };
    $tests[$transport . ' matches explicit and default Paths at segment boundaries'] = function () use ($transport, $origin) {
        foreach (array('/private' => true, '/private/item' => true, '/privateer' => false, '/' => false) as $path => $matches)
            jarSame($matches ? array('sid=private') : array(), jarRoundTrip($transport, array('sid=private; Path=/private'), $origin . '/start', $origin . $path), 'Path match: ' . $path);
        jarSame(array(), jarRoundTrip($transport, array('sid=default'), $origin . '/private/login', $origin . '/public'), 'Default Path uses the source directory');
        jarSame(array('sid=default'), jarRoundTrip($transport, array('sid=default; Path=invalid'), $origin . '/private/login', $origin . '/private/next'), 'Invalid explicit Path falls back to default');
    };
    $tests[$transport . ' retains duplicate names and sorts longer Paths then creation order'] = function () use ($transport, $origin) {
        jarSame(array('first=one; second=two; id=inner; id=base'), jarRoundTrip($transport, array('first=one; Path=/private', 'id=base; Path=/', 'second=two; Path=/private', 'id=inner; Path=/private'), $origin . '/', $origin . '/private/item'), 'Longer Paths sort before older root entries');
    };
    $tests[$transport . ' preserves host-only and Domain tuples with the same name'] = function () use ($transport, $origin) {
        jarSame(array('id=host; id=domain'), jarRoundTrip($transport, array('id=host; Path=/', 'id=domain; Domain=tracker.example; Path=/'), $origin . '/', $origin . '/'), 'The host-only flag participates in tuple identity');
    };
    $tests[$transport . ' applies expiry Max-Age precedence deletion empty values and saturation'] = function () use ($transport, $origin) {
        $cookies = array('old=gone; Expires=Thu, 01 Jan 1970 00:00:00 GMT', 'override=kept; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Max-Age=60', 'deleted=gone; Max-Age=0', 'negative=gone; Max-Age=-1', 'huge=kept; Max-Age=' . str_repeat('9', 100), 'empty=; Path=/', 'invalid=kept; Max-Age=bogus; Expires=bogus');
        jarSame(array('override=kept; huge=kept; empty=; invalid=kept'), jarRoundTrip($transport, $cookies, $origin . '/', $origin . '/'), 'Expiry rules select the live tuples');
        $client = jarClient($transport, array(jarReply(array('sid=old; Path=/')), jarReply(array('sid=new; Path=/; Max-Age=0')), jarReply()));
        $client->fetch($origin . '/'); $client->fetch($origin . '/'); $client->fetch($origin . '/');
        jarSame(array(), jarFields(jarRequests($client)[2]), 'Max-Age deletion removes the stored tuple');
    };
    $tests[$transport . ' preserves manual overrides including collisions with response values'] = function () use ($transport, $origin) {
        foreach (array('caller', 'response') as $value)
            foreach (array('/private', '/public') as $path)
                jarSame(array('sid=' . $value), jarRoundTrip($transport, array('sid=response; Path=/private'), $origin . '/', $origin . $path, array('sid' => $value)), 'Explicit caller cookies override matching and nonmatching response scope');
    };
    $tests[$transport . ' raw Cookie takes precedence as the sole case insensitive field'] = function () use ($transport, $origin) {
        $client = jarClient($transport, array(jarReply(array('jar=secret')), jarReply()));
        $client->cookies = array('manual' => 'secret');
        $client->fetch($origin . '/');
        $client->rawheaders['cOoKiE'] = 'raw=only';
        $client->rawheaders['Cookie'] = 'later=ignored';
        $client->fetch($origin . '/');
        jarSame(array('raw=only'), jarFields(jarRequests($client)[1]), 'The writer emits exactly the raw Cookie field');
    };
    $tests[$transport . ' passcookies false prevents automatic import but keeps manual cookies'] = function () use ($transport, $origin) {
        $client = jarClient($transport, array(jarReply(array('sid=secret')), jarReply()));
        $client->cookies = array('manual' => 'yes');
        $client->passcookies = false;
        $client->fetch($origin . '/');
        $client->fetch($origin . '/');
        jarSame(array('manual=yes'), jarFields(jarRequests($client)[1]), 'Disabled automatic capture leaves caller cookies usable');
    };
    $tests[$transport . ' explicit setcookies exports legacy facade without losing request scope'] = function () use ($transport, $origin, $scheme) {
        $client = jarClient($transport, array(jarReply(array('sid=private; Path=/private')), jarReply(), jarReply()));
        $client->fetch($origin . '/private/login'); $client->setcookies();
        jarSame(array('sid' => 'private'), $client->cookies, 'Existing login adapters can still save flat cookies');
        $client->fetch($origin . '/public');
        $client->fetch($scheme . '://other.example/private');
        $wire = jarRequests($client);
        jarSame(array(), jarFields($wire[1]), 'The exported facade cannot bypass Path');
        jarSame(array(), jarFields($wire[2]), 'The exported facade cannot bypass host-only scope');
    };
    $tests[$transport . ' deletion does not resurrect an exported facade and changed manual values override'] = function () use ($transport, $origin) {
        $client = jarClient($transport, array(jarReply(array('sid=old')), jarReply(array('sid=old; Max-Age=0')), jarReply(), jarReply()));
        $client->fetch($origin . '/'); $client->setcookies();
        $client->fetch($origin . '/'); $client->setcookies();
        $client->fetch($origin . '/');
        jarSame(array(), jarFields(jarRequests($client)[2]), 'A deleted exported value stays suppressed');
        $client->cookies['sid'] = 'manual';
        $client->fetch($origin . '/');
        jarSame(array('sid=manual'), jarFields(jarRequests($client)[3]), 'An explicitly changed caller value wins');
    };
    $tests[$transport . ' does not import stale response headers after transport failure'] = function () use ($transport, $origin, $scheme) {
        $client = jarClient($transport, array(jarReply(array('sid=private')), false, jarReply()));
        $client->fetch($origin . '/');
        jarSame(false, $client->fetch($scheme . '://failed.example/'), 'The transport fails');
        $client->setcookies();
        $client->fetch($scheme . '://other.example/');
        jarSame(array(), jarFields(jarRequests($client)[$transport === 'socket' ? 1 : 2]), 'Stale response cookies never reach a later host');
    };
    $tests[$transport . ' same-origin redirects obey Path and passcookies and retain upstream crossing policy'] = function () use ($transport, $origin, $scheme) {
        foreach (array(true, false) as $enabled) {
            $client = jarClient($transport, array("HTTP/1.1 302 Found\r\nSet-Cookie: root=yes; Path=/\r\nSet-Cookie: private=no; Path=/private\r\nLocation: /public\r\n\r\n", jarReply()));
            $client->passcookies = $enabled;
            $client->fetch($origin . '/start');
            jarSame($enabled ? array('root=yes') : array(), jarFields(jarRequests($client)[1]), 'Same-origin redirect imports eligible cookies only');
        }
        $client = jarClient($transport, array("HTTP/1.1 302 Found\r\nSet-Cookie: parent=private; Domain=example.co.uk\r\nLocation: " . $scheme . "://b.example.co.uk/\r\n\r\n", jarReply()));
        $client->cookies = array('manual' => 'private');
        $client->fetch($scheme . '://a.example.co.uk/');
        jarSame(array(), jarFields(jarRequests($client)[1]), 'An origin crossing keeps upstream credential-drop policy');
    };
    $tests[$transport . ' refuses malformed prefix and oversized cookie while keeping valid values'] = function () use ($transport, $origin) {
        jarSame(array('valid=yes'), jarRoundTrip($transport, array('invalid name=no', '__Secure-id=no', '__Host-id=no; Path=/', 'big=' . str_repeat('x', 4096), 'valid=yes'), $origin . '/', $origin . '/'), 'Rejected cookies do not displace an ordinary response cookie');
    };
    $tests[$transport . ' bounds per-domain capacity and admits a full legitimate jar'] = function () use ($transport, $origin) {
        $cookies = array();
        for ($i = 0; $i < 51; $i++) $cookies[] = 'c' . $i . '=v; Path=/';
        $fields = jarRoundTrip($transport, $cookies, $origin . '/', $origin . '/');
        jarSame(1, count($fields), 'A full jar still has one Cookie field');
        jarSame(50, count(explode('; ', $fields[0])), 'The domain capacity is bounded at fifty');
        jarSame(false, in_array('c0=v', explode('; ', $fields[0]), true), 'The least recently used ordinary cookie is evicted');
        jarSame(true, in_array('c50=v', explode('; ', $fields[0]), true), 'The newest cookie is admitted');
    };
}
$tests['an expired insertion cannot evict live cookies from a full jar'] = function () {
    $cookies = array();
    for ($i = 0; $i < 50; $i++) $cookies[] = 'c' . $i . '=v';
    $client = jarClient('socket', array(jarReply($cookies), jarReply(array('expired=gone; Max-Age=0')), jarReply()));
    $client->fetch('http://tracker.example/'); $client->fetch('http://tracker.example/'); $client->fetch('http://tracker.example/');
    jarSame(50, count(explode('; ', jarFields(jarRequests($client)[2])[0])), 'Immediate expiry removes no unrelated live tuple');
};
$tests['a destination can replace a blocked source tuple before its next same-origin redirect'] = function () {
    $client = jarClient('socket', array("HTTP/1.1 302 Found\r\nSet-Cookie: sid=source; Path=/\r\nLocation: http://tracker.example:8081/next\r\n\r\n", "HTTP/1.1 302 Found\r\nSet-Cookie: sid=destination; Path=/\r\nLocation: http://tracker.example:8081/last\r\n\r\n", jarReply()));
    $client->fetch('http://tracker.example:8080/start');
    $wire = jarRequests($client);
    jarSame(array(), jarFields($wire[1]), 'The crossing suppresses the source tuple');
    jarSame(array('sid=destination'), jarFields($wire[2]), 'The destination replacement owns the later same-origin hop');
};
$tests['Domain matching requires a DNS label boundary'] = function () {
    jarSame(array(), jarRoundTrip('socket', array('sid=bad; Domain=tracker.example'), 'http://eviltracker.example/', 'http://eviltracker.example/'), 'A textual suffix is not a parent domain');
};
$tests['delayed explicit setcookies does not restart Max-Age when automatic capture is disabled'] = function () {
    $client = jarClient('socket', array(jarReply(array('sid=short; Max-Age=1')), jarReply()));
    $client->passcookies = false;
    $client->fetch('http://tracker.example/');
    sleep(2);
    $client->setcookies();
    $client->fetch('http://tracker.example/');
    jarSame(array(), jarFields(jarRequests($client)[1]), 'Max-Age starts at response receipt rather than delayed explicit export');
};
$tests['delayed import of expired cookies cannot displace a full live jar'] = function () {
    foreach (array('age', 'calendar') as $mode) {
        $expiry = $mode === 'age' ? 'Max-Age=1' : 'Expires=' . gmdate('D, d M Y H:i:s \G\M\T', time() + 1);
        $cookies = array();
        for ($i = 0; $i < 50; $i++) $cookies[] = 'c' . $i . '=v';
        $client = jarClient('socket', array(jarReply($cookies), jarReply(array('expired=gone; ' . $expiry)), jarReply()));
        $client->fetch('http://tracker.example/'); $client->passcookies = false;
        $client->fetch('http://tracker.example/'); sleep(2); $client->setcookies();
        $client->fetch('http://tracker.example/');
        jarSame(50, count(explode('; ', jarFields(jarRequests($client)[2])[0])), 'Insertion-time expiry cannot evict live tuples');
    }
};
$tests['cleared exported facade values do not remain in hidden provenance memory'] = function () {
    $plan = array();
    for ($i = 0; $i < 1000; $i++) $plan[] = jarReply(array('c' . $i . '=' . str_repeat('v', 4000)));
    $plan[] = jarReply();
    $client = jarClient('socket', $plan);
    for ($i = 0; $i < 1000; $i++) {
        $client->fetch('http://h' . $i . '.example/'); $client->setcookies();
        $client->cookies = array(); $client->finish();
    }
    $client->fetch('http://fresh.example/');
    $provenance = new ReflectionProperty(Snoopy::class, 'flattenedResponseCookies');
    if (PHP_VERSION_ID < 80100) $provenance->setAccessible(true);
    jarSame(array(), $provenance->getValue($client), 'A cleared facade releases hidden response values');
};
$tests['capacity eviction honors recent Path use and preserves Secure cookies first'] = function () {
    foreach (array(false, true) as $secure) {
        $cookies = array('c0=protected; Path=/private' . ($secure ? '; Secure' : ''));
        for ($i = 1; $i < 50; $i++) $cookies[] = 'c' . $i . '=old; Path=/public';
        $client = jarClient('curl', array(jarReply($cookies), jarReply(array('c50=new; Path=/')), jarReply()));
        $client->fetch('https://tracker.example/');
        $client->fetch('https://tracker.example/' . ($secure ? 'unrelated' : 'private'));
        $client->fetch('https://tracker.example/private');
        jarSame(array('c0=protected; c50=new'), jarFields(jarRequests($client)[2]), 'Recent use or Secure priority preserves the original private cookie');
    }
};
$tests['global capacity remains bounded across distinct response hosts and visible on eviction'] = function () {
    global $jarRoot;
    $plan = array_fill(0, 8, jarReply());
    $client = jarClient('socket', $plan);
    for ($host = 0; $host < 7; $host++) {
        $cookies = array();
        for ($i = 0; $i < 50; $i++) $cookies[] = 'c' . $i . '=v';
        $client->plan[$host] = jarReply($cookies);
    }
    for ($host = 0; $host < 7; $host++) $client->fetch('http://h' . $host . '.example/');
    $client->fetch('http://h0.example/');
    jarSame(array(), jarFields(jarRequests($client)[7]), 'A global overflow evicts the oldest fifty cookies');
    $jar = new ReflectionProperty(Snoopy::class, 'responseCookieJar');
    if (PHP_VERSION_ID < 80100) $jar->setAccessible(true);
    jarSame(300, count($jar->getValue($client)), 'The global jar is bounded at three hundred');
    jarSame(true, strpos(file_get_contents($jarRoot . '/application.log'), 'response-cookie-capacity-evicted') !== false,
        'Capacity eviction is visible without cookie names or values');
};
$tests['one response accepts only its bounded cookie headers and refuses truncated or oversized lines'] = function () {
    $cookies = array();
    for ($i = 0; $i < 110; $i++) $cookies[] = 'c' . $i . '=v; Path=/' . $i;
    $client = jarClient('socket', array(jarReply($cookies), jarReply()));
    $client->fetch('http://tracker.example/');
    $jar = new ReflectionProperty(Snoopy::class, 'responseCookieJar');
    if (PHP_VERSION_ID < 80100) $jar->setAccessible(true);
    jarSame(50, count($jar->getValue($client)), 'The per-domain cap also bounds a large response');
    $names = array_column($jar->getValue($client), 'name');
    jarSame(false, in_array('c109', $names, true), 'Headers after the response scan cap are not imported');
    $client = jarClient('socket', array(jarReply(array('big=' . str_repeat('x', 4094), 'normal=yes')), jarReply()));
    $client->_maxlinelen = 16384;
    $client->fetch('http://tracker.example/'); $client->fetch('http://tracker.example/');
    jarSame(array('normal=yes'), jarFields(jarRequests($client)[1]), 'A value over the 4096-byte cookie bound is refused');
    $client = jarClient('socket', array(jarReply(array('big=' . str_repeat('x', 4093))), jarReply()));
    $client->_maxlinelen = 16384;
    $client->fetch('http://tracker.example/'); $client->fetch('http://tracker.example/');
    jarSame(array('big=' . str_repeat('x', 4093)), jarFields(jarRequests($client)[1]), 'The exact 4096-byte name/value bound is accepted');
};
$tests['explicit setcookies cannot renew an already expired response'] = function () {
    $client = jarClient('socket', array(jarReply(array('sid=short; Max-Age=1')), jarReply()));
    $client->fetch('http://tracker.example/'); $client->setcookies();
    sleep(2);
    $client->setcookies();
    jarSame(array(), $client->cookies, 'An expired exported value is retired before another cache save');
    $client->fetch('http://tracker.example/');
    jarSame(array(), jarFields(jarRequests($client)[1]), 'Exporting a facade cannot renew expired Max-Age');
};
$tests['legacy setcookies without a fetched URL retains its manual facade'] = function () {
    $client = jarClient('socket', array(jarReply()));
    $client->headers = array('Set-Cookie: sid=legacy; Path=/private');
    $client->setcookies();
    jarSame(array('sid' => 'legacy'), $client->cookies, 'Header-only legacy callers still export cookies');
    $client->fetch('http://other.example/public');
    jarSame(array('sid=legacy'), jarFields(jarRequests($client)[0]), 'An unattributed legacy cookie remains explicit manual input');
};
$tests['setcookies preserves equal manual input and scopes a newly replaced manual value'] = function () {
    foreach (array('response' => true, 'old' => false) as $manual => $kept) {
        $client = jarClient('socket', array(jarReply(array('sid=response; Path=/private')), jarReply()));
        $client->cookies['sid'] = $manual;
        $client->fetch('http://tracker.example/private/login'); $client->setcookies();
        $client->fetch('http://tracker.example/public');
        jarSame($kept ? array('sid=response') : array(), jarFields(jarRequests($client)[1]), 'Only an equal preexisting caller value remains manual');
    }
};
$tests['cross-port redirects suppress the saved jar but an explicit later fetch can reuse it'] = function () {
    $client = jarClient('socket', array("HTTP/1.1 302 Found\r\nSet-Cookie: sid=source; Path=/\r\nLocation: http://tracker.example:8081/next\r\n\r\n", jarReply(array('dest=fresh')), jarReply()));
    $client->fetch('http://tracker.example:8080/start');
    jarSame(array(), jarFields(jarRequests($client)[1]), 'The cross-port redirect does not revive source credentials');
    $client->fetch('http://tracker.example:8081/next');
    jarSame(array('sid=source; dest=fresh'), jarFields(jarRequests($client)[2]), 'A new explicit request uses ordinary host and Path scope');
};
$tests['HTTPS downgrade suppresses plain and Secure cookies but later destination redirects can set their own'] = function () {
    $client = jarClient('curl', array("HTTP/1.1 302 Found\r\nSet-Cookie: plain=source; Path=/\r\nSet-Cookie: secure=source; Secure; Path=/\r\nLocation: http://tracker.example/next\r\n\r\n", "HTTP/1.1 302 Found\r\nSet-Cookie: dest=fresh; Path=/\r\nLocation: http://tracker.example/last\r\n\r\n", jarReply()));
    $client->proxy_proto = 'https';
    $client->fetch('https://tracker.example/start');
    $wire = jarRequests($client);
    jarSame(array(), jarFields($wire[1]), 'A downgrade receives neither kind of source cookie');
    jarSame(array('dest=fresh'), jarFields($wire[2]), 'The destination can establish its own cookie on its subsequent same-origin hop');
};
$tests['redirect login preserves the facade session saved and loaded by existing loginmgr adapters'] = function () {
    require_once(__DIR__ . '/../../plugins/loginmgr/accounts.php');
    $login = jarClient('socket', array("HTTP/1.1 302 Found\r\nSet-Cookie: sid=redirect-session; Path=/private\r\nLocation: /private/landing\r\n\r\n", jarReply()));
    $login->fetch('http://tracker.example/private/login'); $login->setcookies();
    jarSame(array('sid' => 'redirect-session'), $login->cookies, 'The final cookie-less response preserves the redirect login facade');
    $session = new privateData('RedirectCookieCompatibility');
    jarSame(true, $session->store($login), 'The real cache saves the redirect session');
    $fresh = jarClient('socket', array(jarReply()));
    jarSame(true, privateData::load('RedirectCookieCompatibility', $fresh)->loaded, 'A new client loads the redirect session');
    $fresh->fetch('http://tracker.example/private/item');
    jarSame(array('sid=redirect-session'), jarFields(jarRequests($fresh)[0]), 'The cached redirect session remains usable');
};
$tests['loginmgr stores its unchanged flat schema and loads it into a fresh client'] = function () {
    require_once(__DIR__ . '/../../plugins/loginmgr/accounts.php');
    $login = jarClient('socket', array(jarReply(array('sid=session; Path=/private'))));
    $login->fetch('http://tracker.example/private/login'); $login->setcookies();
    $session = new privateData('CookieCompatibility');
    jarSame(true, $session->store($login), 'The real loginmgr cache saves the login facade');
    $fresh = jarClient('socket', array(jarReply()));
    $loaded = privateData::load('CookieCompatibility', $fresh);
    jarSame(true, $loaded->loaded, 'The real loginmgr cache loads into a fresh client');
    jarSame(array('sid' => 'session'), $fresh->cookies, 'The persisted flat array is unchanged');
    $fresh->fetch('http://tracker.example/public');
    jarSame(array('sid=session'), jarFields(jarRequests($fresh)[0]), 'A loaded legacy session retains existing flat request behavior');
};
$tests['Secure prefix and overwrite protection retain HTTPS scope on a reused client'] = function () {
    $client = jarClient('curl', array(jarReply(array('sid=secret; Secure; Path=/', '__Secure-ok=yes; Secure', '__Host-ok=yes; Secure; Path=/', '__Host-no=no; Secure; Path=/private', '__Host-domain=no; Secure; Path=/; Domain=tracker.example')), jarReply()));
    $client->fetch('https://tracker.example/');
    $client->fetch('https://tracker.example/');
    jarSame(array('sid=secret; __Secure-ok=yes; __Host-ok=yes'), jarFields(jarRequests($client)[1]), 'Valid prefixes survive on HTTPS');
    $client->proxy_proto = 'https'; // The HTTPS writer can address plain HTTP through an HTTPS proxy.
    global $jarRoot;
    file_put_contents($jarRoot . '/plan', serialize(array(jarReply(array('sid=attacker; Path=/', 'bad=secret; Secure')), jarReply(), jarReply())));
    $client->fetch('http://tracker.example/');
    $client->fetch('http://tracker.example/');
    $client->fetch('https://tracker.example/');
    $wire = jarRequests($client);
    jarSame(array(), jarFields($wire[2]), 'Secure cookies stay off an HTTP request');
    jarSame(array(), jarFields($wire[3]), 'HTTP cannot set Secure or overwrite a secure cookie');
    jarSame(array('sid=secret; __Secure-ok=yes; __Host-ok=yes'), jarFields($wire[4]), 'The original HTTPS cookies remain intact');
};
$tests['the HTTP writer sends scoped cookies over a real loopback connection'] = function () {
    global $jarRoot;
    $root = $jarRoot . '/http'; mkdir($root, 0700);
    file_put_contents($root . '/router.php', '<?php if (parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH) === "/private/login") { header("Set-Cookie: id=base; Path=/", false); header("Set-Cookie: id=inner; Path=/private", false); } echo $_SERVER["HTTP_COOKIE"] ?? "";');
    $server = TestHttpServer::start($root, array(), array('-c', __DIR__ . '/../php-test.ini'), $root . '/router.php');
    try {
        $client = new ResponseCookieLoopback(); $client->block_private = false;
        $origin = 'http://tracker.example:' . $server->port();
        jarSame(true, $client->fetch($origin . '/private/login'), 'Real HTTP source completes');
        jarSame(true, $client->fetch($origin . '/private/item'), 'Real HTTP target completes');
        jarSame('id=inner; id=base', $client->results, 'The loopback server receives both Path scoped names');
        jarSame(true, $client->fetch($origin . '/public'), 'Real HTTP public path completes');
        jarSame('id=base', $client->results, 'The private Path cookie stays off a real public request');
    } finally { $server->close(); TestTree::remove($root); }
};


$tests['chain reset before another same-origin redirect'] = function () {
    $client = jarClient('socket', array("HTTP/1.1 302 Found\r\nSet-Cookie: sid=source; Path=/\r\nLocation: http://tracker.example:8081/next\r\n\r\n", jarReply(), "HTTP/1.1 302 Found\r\nLocation: /last\r\n\r\n", jarReply()));
    $client->fetch('http://tracker.example:8080/start');
    $client->fetch('http://tracker.example:8081/start');
    jarSame(array('sid=source'), jarFields(jarRequests($client)[3]), 'The new deliberate chain clears old blocked keys');
};
$tests['deferred capture after toggling automatic capture'] = function () {
    $client = jarClient('socket', array(jarReply(array('sid=old')), jarReply(array('sid=new')), jarReply()));
    $client->fetch('http://tracker.example/');
    $client->passcookies = false;
    $client->fetch('http://tracker.example/'); $client->setcookies();
    $client->fetch('http://tracker.example/');
    jarSame(array('sid=new'), jarFields(jarRequests($client)[2]), 'A new response has its own deferred-capture flag');
};
$tests['pathless URL uses root cookie path'] = function () {
    jarSame(array('sid=ok'), jarRoundTrip('socket', array('sid=ok; Path=/'), 'http://tracker.example/', 'http://tracker.example'), 'A pathless URL targets root');
};
$tests['oversized attribute header is refused'] = function () {
    $client = jarClient('socket', array(jarReply(array('sid=bad; Ignore=' . str_repeat('x', 8200))), jarReply()));
    $client->_maxlinelen = 16384;
    $client->fetch('http://tracker.example/'); $client->fetch('http://tracker.example/');
    jarSame(array(), jarFields(jarRequests($client)[1]), 'The complete response header is bounded independently of name/value');
};
$tests['cookie name whitespace is trimmed'] = function () {
    jarSame(array('sid=ok'), jarRoundTrip('socket', array('sid =ok'), 'http://tracker.example/', 'http://tracker.example/'), 'The cookie name is trimmed');
};
$tests['cookie value whitespace is trimmed'] = function () {
    jarSame(array('sid=ok'), jarRoundTrip('socket', array('sid= ok'), 'http://tracker.example/', 'http://tracker.example/'), 'The cookie value is trimmed');
};
$tests['uppercase Domain replaces lowercase tuple'] = function () {
    $client = jarClient('socket', array(jarReply(array('sid=upper; Domain=TRACKER.EXAMPLE')), jarReply(array('sid=lower; Domain=tracker.example')), jarReply()));
    $client->fetch('http://tracker.example/'); $client->fetch('http://tracker.example/'); $client->fetch('http://tracker.example/');
    jarSame(array('sid=lower'), jarFields(jarRequests($client)[2]), 'Domain case does not create separate identities');
};
$tests['foreign Domain cannot seed the victim jar'] = function () {
    jarSame(array(), jarRoundTrip('socket', array('sid=attack; Domain=victim.example'), 'http://attacker.example/', 'http://victim.example/'), 'An unrelated source cannot seed a destination cookie');
};
$tests['Host prefix requires explicit root Path'] = function () {
    jarSame(array(), jarRoundTrip('curl', array('__Host-sid=bad; Secure'), 'https://tracker.example/', 'https://tracker.example/'), 'Default root Path cannot replace explicit root Path for Host prefix');
};
$tests['stale entries are purged between receipt and quota enforcement'] = function () {
    $cookies = array(); for ($i=0; $i<50; $i++) $cookies[] = 'c'.$i.'=v';
    $client=jarClient('socket',array(jarReply($cookies),jarReply()));
    $client->fetch('http://tracker.example/');
    $prop=new ReflectionProperty(Snoopy::class,'responseCookieJar');
    if (PHP_VERSION_ID < 80100) $prop->setAccessible(true);
    $jar=$prop->getValue($client); foreach($jar as &$entry) if($entry['name']==='c49') $entry['expires']=time()-1; unset($entry); $prop->setValue($client,$jar);
    $client->headers=array('Set-Cookie: new=live');
    $capture=new ReflectionMethod(Snoopy::class,'captureResponseCookies'); if (PHP_VERSION_ID < 80100) $capture->setAccessible(true); $capture->invoke($client,'http://tracker.example/');
    $client->fetch('http://tracker.example/');
    $fields=jarFields(jarRequests($client)[1]);
    jarSame(true, strpos($fields[0],'c0=v')!==false,'A cookie expiring during transport frees quota before insertion');
    jarSame(50,count(explode('; ',$fields[0])),'Expiry before quota leaves fifty live entries');
};
$tests['replacement preserves original creation rank'] = function () {
    $client=jarClient('socket',array(jarReply(array('a=old; Path=/','b=old; Path=/')),jarReply(array('a=new; Path=/')),jarReply()));
    $client->fetch('http://tracker.example/');$client->fetch('http://tracker.example/');$client->fetch('http://tracker.example/');
    jarSame(array('a=new; b=old'),jarFields(jarRequests($client)[2]),'Replacement preserves its tuple creation rank');
};
foreach(array('child','parent','path') as $overlap) {
    $tests['secure overwrite '.$overlap.' overlap'] = function () use($overlap) {
        global $jarRoot;
        $secure='sid=secret; Secure; Path='.($overlap==='path'?'/private':'/').($overlap==='child'?'; Domain=example.com':'');
        $attack='sid=attack; Path='.($overlap==='path'?'/public':'/').($overlap==='parent'?'; Domain=example.com':'');
        $client=jarClient('curl',array(jarReply(array($secure)),jarReply(array($attack)),jarReply()));
        $client->fetch('https://a.example.com/'); $client->proxy_proto='https';
        $client->fetch('http://a.example.com/'); $client->fetch('http://a.example.com/'.($overlap==='path'?'public':''));
        jarSame($overlap==='path'?array('sid=attack'):array(),jarFields(jarRequests($client)[2]),'Secure overwrite checks both domain directions and only overlapping paths');
    };
}
$tests['per-domain overflow preserves unrelated host cookies'] = function () {
    $cookies=array();for($i=0;$i<51;$i++)$cookies[]='c'.$i.'=v';
    $client=jarClient('socket',array(jarReply(array('old=kept')),jarReply($cookies),jarReply()));
    $client->fetch('http://old.example/');$client->fetch('http://full.example/');$client->fetch('http://old.example/');
    jarSame(array('old=kept'),jarFields(jarRequests($client)[2]),'Domain overflow may evict only that domain');
};
$tests['positive deferred import actually captures while disabled'] = function () {
    $client=jarClient('socket',array(jarReply(array('sid=live; Max-Age=60')),jarReply()));$client->passcookies=false;
    $client->fetch('http://tracker.example/');$client->setcookies();$client->fetch('http://tracker.example/');
    jarSame(array('sid=live'),jarFields(jarRequests($client)[1]),'Explicit capture still works when automatic capture is disabled');
};

$failures = 0;
    foreach ($tests as $name => $test) {
        $jarClients = array();
        try { $test(); echo 'ok - ', $name, "\n"; }
        catch (Throwable $error) { $failures++; echo 'not ok - ', $name, ': ', $error->getMessage(), "\n"; }
        finally { foreach ($jarClients as $client) $client->finish(); }
    }
} finally {
    TestTree::remove($jarRoot);
    if ($jarConfigurationChanged) {
        if ($oldCurl === null) unset($pathToExternals['curl']); else $pathToExternals['curl'] = $oldCurl;
        if ($oldRoot === false) putenv('SNOOPY_COOKIE_ROOT'); else putenv('SNOOPY_COOKIE_ROOT=' . $oldRoot);
    }
}
echo count($tests), ' tests, ', $failures, " failures\n";
exit($failures ? 1 : 0);
