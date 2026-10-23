<?php

/**
 * Four loginmgr accounts -- ABTorrents, RevolutionTT, Tfile and Redacted --
 * used to name a tracker's domain in their own source and post the account's
 * login and password to whoever answered there. Each now takes its origin
 * from plugins/loginmgr/conf.php, ships with none, and until one is set
 * claims no URL, sends nothing at all, and says in the log what to set.
 *
 * The assertions that matter are made on the wire: a real Snoopy against a
 * TLS listener that records every connection and every byte, so "no request
 * is made" is read off what arrived rather than off a return value.
 */

$coTemp = sys_get_temp_dir() . '/loginmgr-origin-' . getmypid();
mkdir($coTemp, 0700);
$_ENV['RU_LOG_FILE'] = $coTemp . '/application.log';
$_ENV['RU_PROFILE_PATH'] = $coTemp . '/share';
mkdir($coTemp . '/share', 0700);

// Every entry point loads php/util.php, and with it conf/config.php, at file
// scope. On the download path accounts.php is then first included from inside
// a method, Snoopy::fetchComplex(), and the plugin configuration it evals
// lands in that method's scope unless accounts.php says otherwise -- so the
// first include here is made from a function too.
require_once(__DIR__ . '/../../../php/util.php');

// The listener signs its own certificate. Without this the credential POST
// would be absent for a reason that is not the gate under test.
$GLOBALS['httpVerifyCertificates'] = false;

function coIncludeFromMethodScope()
{
    require_once(__DIR__ . '/../../../plugins/loginmgr/accounts.php');
    foreach (array('ABTorrents', 'RevolutionTT', 'Tfile', 'Redacted') as $name) {
        require_once(__DIR__ . '/../../../plugins/loginmgr/accounts/' . $name . '.php');
    }
}
coIncludeFromMethodScope();

// name => array(setting, the request the login makes, the body it posts)
$coAccounts = array(
    'ABTorrents' => array('abTorrentsOrigin', '/login.php', '/takelogin.php',
        'username=fake-user&password=fake-pass&submitme=X'),
    'RevolutionTT' => array('revolutionTTOrigin', '', '/takelogin.php',
        'username=fake-user&password=fake-pass'),
    'Tfile' => array('tfileOrigin', '/forum/login.php', '/forum/login.php',
        'username=fake-user&password=fake-pass&login=%C2%F5%EE%E4'),
    'Redacted' => array('redactedOrigin', '/login.php', '/login.php',
        'username=fake-user&password=fake-pass&keeplogged=1&login=Login'),
);

function coSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true)
        );
    }
}

function coConfigure($setting, $value)
{
    $GLOBALS[$setting] = $value;
}

function coLog()
{
    global $log_file;
    return is_file($log_file) ? file_get_contents($log_file) : '';
}

// The refusal is logged once per account and process; a test that reads the
// log starts from a clean latch and an empty log.
function coResetLog()
{
    global $log_file;
    if (property_exists('commonAccount', 'unconfiguredLogged')) {
        $latch = new ReflectionProperty('commonAccount', 'unconfiguredLogged');
        if (PHP_VERSION_ID < 80100) {
            $latch->setAccessible(true);
        }
        $latch->setValue(null, array());
    }
    if (is_file($log_file)) {
        unlink($log_file);
    }
}

// A privateData that never touches rCache, so a refresh does not write into
// the profile and a failed one does not report a cache error instead.
class CoProbeData
{
    public $loaded = false;
    public $stored = 0;
    public $removed = 0;

    public function store($client)
    {
        $this->stored++;
        return true;
    }

    public function remove()
    {
        $this->removed++;
    }
}

// Counts what the account asked the client to fetch, and fetches it. The
// listener says what arrived; this says whether anything was attempted, so a
// request that failed to leave is told from one that was never made.
class CoCountingSnoopy extends Snoopy
{
    public $attempts = array();

    public function fetch($URI, $method = "GET", $content_type = "", $body = "")
    {
        $this->attempts[] = array($URI, $method, $body);
        return parent::fetch($URI, $method, $content_type, $body);
    }
}

/**
 * Starts a TLS listener on 127.0.0.1 that records every connection and every
 * byte of it. Returns array(process, pipes, port).
 */
function coListen($temp)
{
    $pem = $temp . '/listener.pem';
    if (!is_file($pem)) {
        $openssl = 'openssl req -x509 -newkey rsa:2048 -nodes'
            . ' -keyout ' . escapeshellarg($temp . '/key.pem')
            . ' -out ' . escapeshellarg($temp . '/cert.pem')
            . ' -days 1 -subj /CN=localhost 2>/dev/null';
        exec($openssl, $output, $code);
        if ($code !== 0 || !is_file($temp . '/cert.pem')) {
            throw new RuntimeException(
                'this test needs the openssl command to make a certificate for its own listener,'
                . ' and it exited ' . $code
            );
        }
        file_put_contents($pem,
            file_get_contents($temp . '/key.pem') . file_get_contents($temp . '/cert.pem'));
    }

    $peer = <<<'PHP'
$context = stream_context_create(array('ssl' => array(
    'local_cert' => $argv[1], 'allow_self_signed' => true, 'verify_peer' => false)));
$server = stream_socket_server('tls://127.0.0.1:0', $errno, $error,
    STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
if ($server === false) exit(2);
$name = stream_socket_get_name($server, false);
fwrite(STDOUT, substr($name, strrpos($name, ':') + 1) . "\n");
fflush(STDOUT);
$seen = array();
$deadline = microtime(true) + 10;
while (microtime(true) < $deadline) {
    $socket = @stream_socket_accept($server, 1);
    if ($socket === false) continue;
    stream_set_timeout($socket, 5);
    $headers = '';
    while (($line = fgets($socket)) !== false) {
        $headers .= $line;
        if ($line === "\r\n" || $line === "\n") break;
    }
    $length = preg_match('/^Content-Length: *([0-9]+)\r?$/mi', $headers, $m) ? (int)$m[1] : 0;
    $body = '';
    while (strlen($body) < $length) {
        $part = fread($socket, $length - strlen($body));
        if ($part === false || $part === '') break;
        $body .= $part;
    }
    $seen[] = array('headers' => $headers, 'body' => $body);
    file_put_contents($argv[2], json_encode($seen));
    $page = '<html>welcome</html>';
    fwrite($socket, "HTTP/1.1 200 OK\r\nContent-Type: text/html\r\nContent-Length: "
        . strlen($page) . "\r\nConnection: close\r\n\r\n" . $page);
    fclose($socket);
    if (count($seen) >= (int)$argv[3]) break;
}
file_put_contents($argv[2], json_encode($seen));
fclose($server);
PHP;

    $record = $temp . '/recorded.json';
    file_put_contents($record, '[]');
    $process = proc_open(
        array(PHP_BINARY, '-d', 'error_reporting=0', '-r', $peer, $pem, $record, '2'),
        array(0 => array('file', '/dev/null', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
        $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('the recording listener did not start');
    }
    $port = trim((string) fgets($pipes[1]));
    if (!ctype_digit($port) || $port === '0') {
        throw new RuntimeException('the recording listener named no port: ' . var_export($port, true)
            . ' / ' . stream_get_contents($pipes[2]));
    }
    return array($process, $pipes, (int) $port, $record);
}

// Everything that arrived, as array(array(method, path, body), ...).
function coArrived($listener, $expected)
{
    list($process, $pipes, $port, $record) = $listener;
    // The listener stops at $expected connections; when fewer arrive it runs
    // to its own deadline, so do not wait on it for the empty case.
    $deadline = microtime(true) + 12;
    while (microtime(true) < $deadline) {
        $seen = json_decode((string) file_get_contents($record), true);
        if (is_array($seen) && count($seen) >= $expected) {
            break;
        }
        usleep(100000);
    }
    $seen = json_decode((string) file_get_contents($record), true);
    $arrived = array();
    foreach (is_array($seen) ? $seen : array() as $connection) {
        preg_match('@^([A-Z]+) ([^ ]+)@', $connection['headers'], $match);
        $arrived[] = array($match[1], $match[2], $connection['body']);
    }
    return $arrived;
}

function coStop($listener)
{
    list($process, $pipes, $port, $record) = $listener;
    proc_terminate($process);
    foreach ($pipes as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }
    proc_close($process);
}

function coRemoveTree($path)
{
    foreach (scandir($path) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $child = $path . '/' . $entry;
        if (is_dir($child) && !is_link($child)) {
            coRemoveTree($child);
        } else {
            unlink($child);
        }
    }
    rmdir($path);
}

// A refresh with no cache of its own, so the credential path is the only
// thing the case measures.
function coRefresh($name, $client)
{
    $probe = 'CoProbe' . $name . 'Account';
    // Without the second argument util.php's autoloader looks for a file
    // named after the probe class and warns that it is missing.
    if (!class_exists($probe, false)) {
        eval('class ' . $probe . ' extends ' . $name . 'Account {
            public $data;
            public function __construct() {
                // An account that names its own site has no constructor;
                // without this the failures on such a tree read as an API
                // difference instead of as where the credentials went.
                if (method_exists(' . var_export($name . 'Account', true) . ', \'__construct\')) {
                    parent::__construct();
                }
                $this->data = new CoProbeData();
            }
            public function getName() { return ' . var_export($name, true) . '; }
            protected function loadData($client = null) { return $this->data; }
        }');
    }
    $account = new $probe();
    $account->check($client, 'fake-user', 'fake-pass', 0);
    return $account;
}

$tests = array(
    'the shipped configuration names no origin for any of the four' => function () {
        global $coAccounts;
        foreach ($coAccounts as $name => $nfo) {
            coSame(true, array_key_exists($nfo[0], $GLOBALS),
                'plugins/loginmgr/conf.php must declare $' . $nfo[0] . ' in the global scope');
        }
        // Read from the file, since the cases above assign to the globals.
        $conf = file_get_contents(__DIR__ . '/../../../plugins/loginmgr/conf.php');
        foreach ($coAccounts as $name => $nfo) {
            coSame(1, preg_match('/^\$' . preg_quote($nfo[0], '/') . " = '';$/m", $conf),
                'plugins/loginmgr/conf.php leaves $' . $nfo[0] . ' to the user');
        }
    },

    'with no origin the refresh sends nothing at all, and says what to set' => function () use ($coTemp) {
        global $coAccounts;
        $listener = coListen($coTemp);
        try {
            foreach ($coAccounts as $name => $nfo) {
                coConfigure($nfo[0], '');
                coResetLog();
                $client = new CoCountingSnoopy();
                $account = coRefresh($name, $client);
                coSame('', $account->url, $name . ' claims no site');
                coSame(array(), $client->attempts, $name . ' asks for no request');
                coSame(0, $account->data->stored + $account->data->removed,
                    $name . ' leaves the session cache alone');
                $log = coLog();
                coSame(1, substr_count($log, "\n"), 'one log line for ' . $name . ': ' . $log);
                foreach (array('loginmgr: ' . $name . ' is not used', '$' . $nfo[0] . ' is not set',
                    'plugins/loginmgr/conf.local.php') as $part) {
                    coSame(true, strpos($log, $part) !== false,
                        'the log line names ' . $part . ': ' . $log);
                }
            }
            coSame(array(), coArrived($listener, 0),
                'and nothing reached a listening peer: not a connection, not a byte');
        } finally {
            coStop($listener);
        }
    },

    'with no origin no URL is claimed, so no caller can select the account' => function () {
        global $coAccounts;
        foreach ($coAccounts as $name => $nfo) {
            coConfigure($nfo[0], '');
            coResetLog();
            $account = coRefresh($name, new CoCountingSnoopy());
            foreach (array('https://tracker.example/login.php', 'https://tracker.example/forum/dl.php?t=1',
                'https://tracker.example/', 'https://tracker.example/takelogin.php') as $url) {
                coSame(false, $account->test($url), $name . ' claims nothing with no origin: ' . $url);
            }
        }
    },

    'the selection path says why it claims nothing, without a refresh' => function () {
        global $coAccounts;
        // commonAccount::test() is the whole of what getAccount() consults, and
        // a download or a search reaches it with no check() ahead of it. Asking
        // it and getting false back has to be explained somewhere.
        foreach ($coAccounts as $name => $nfo) {
            coConfigure($nfo[0], '');
            coResetLog();
            $account = coRefresh($name, new CoCountingSnoopy());
            coResetLog();
            coSame(false, $account->test('https://tracker.example/login.php'),
                $name . ' claims nothing');
            coSame(false, $account->test('https://tracker.example/forum/dl.php?t=1'),
                $name . ' claims nothing at all');
            $log = coLog();
            coSame(1, substr_count($log, "\n"),
                'one log line for ' . $name . ', not one per URL: ' . $log);
            coSame(true, strpos($log, '$' . $nfo[0] . ' is not set') !== false,
                'and it names the setting: ' . $log);
        }
    },

    'a configured origin is where the landing page and the credentials go' => function () use ($coTemp) {
        global $coAccounts;
        foreach ($coAccounts as $name => $nfo) {
            list($setting, $landing, $post, $body) = $nfo;
            $listener = coListen($coTemp);
            try {
                $origin = 'https://localhost:' . $listener[2];
                coConfigure($setting, $origin);
                $client = new CoCountingSnoopy();
                $account = coRefresh($name, $client);
                coSame($origin, $account->url, $name . ' takes its site from $' . $setting);
                coSame(array(
                    array('GET', $landing === '' ? '/' : $landing, ''),
                    array('POST', $post, $body),
                ), coArrived($listener, 2), $name . ': the landing page then the credential POST arrive');
                coSame(1, $account->data->stored, $name . ': the renewed session is cached');
            } finally {
                coStop($listener);
            }
        }
    },

    'a configured origin claims its own host and nothing that merely looks like it' => function () {
        global $coAccounts;
        $paths = array('ABTorrents' => '/login.php', 'RevolutionTT' => '/login.php',
            'Tfile' => '/forum/dl.php?t=1', 'Redacted' => '/login.php');
        foreach ($coAccounts as $name => $nfo) {
            coConfigure($nfo[0], 'https://tracker.example');
            $account = coRefresh($name, new CoCountingSnoopy());
            $path = $paths[$name];
            foreach (array(
                'https://tracker.example' . $path => true,
                'https://TRACKER.EXAMPLE' . $path => true,
                'https://tracker.example@evil.test' . $path => false,
                'https://tracker.example.evil.test' . $path => false,
                'https://evil.test/x/tracker.example' . $path => false,
                'https://other.example' . $path => false,
                '' => false,
            ) as $url => $expected) {
                coSame($expected, $account->test($url), $name . ' test(' . var_export($url, true) . ')');
            }
        }
    },

    'a value that is not an https origin disables the account and is not quoted' => function () use ($coTemp) {
        global $coAccounts;
        $listener = coListen($coTemp);
        try {
            foreach ($coAccounts as $name => $nfo) {
                foreach (array('http://tracker.example', 'tracker.example', 'https://user:pass@tracker.example',
                    'https://tracker.example/login.php', 'https://tracker.example?secret=1',
                    'https://tracker.example#x', 'https://tra cker.example', 'https://tracker..example',
                    'https://tracker.example:0', ' https://tracker.example', array('https://tracker.example'),
                    null, 0) as $configured) {
                    coConfigure($nfo[0], $configured);
                    $client = new CoCountingSnoopy();
                    $account = coRefresh($name, $client);
                    coSame('', $account->url, $name . ' with ' . var_export($configured, true));
                    coSame(array(), $client->attempts,
                        $name . ' asks for nothing with ' . var_export($configured, true));
                }
                coConfigure($nfo[0], 'https://tracker.example?secret=1');
                coResetLog();
                coRefresh($name, new CoCountingSnoopy());
                coSame(true, strpos(coLog(), '$' . $nfo[0] . ' is not an https origin') !== false,
                    $name . ': the reason is classified: ' . coLog());
                coSame(false, strpos(coLog(), 'secret'),
                    $name . ': the value is not quoted, since it may carry a password');
                coConfigure($nfo[0], array('https://tracker.example'));
                coResetLog();
                coRefresh($name, new CoCountingSnoopy());
                coSame(true, strpos(coLog(), '$' . $nfo[0] . ' is not a string') !== false,
                    $name . ': a value of another type has its own reason');
            }
            coSame(array(), coArrived($listener, 0), 'and none of it reached a listening peer');
        } finally {
            coStop($listener);
        }
    },

    'no account names a tracker domain in its own source any more' => function () {
        global $coAccounts;
        foreach ($coAccounts as $name => $nfo) {
            $source = file_get_contents(__DIR__ . '/../../../plugins/loginmgr/accounts/' . $name . '.php');
            // The string literals only: a comment may well name a host as an
            // example, and an example is not what gets fetched.
            $named = array();
            foreach (token_get_all($source) as $token) {
                if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING
                    && preg_match('@https?://@i', $token[1])) {
                    $named[] = $token[1];
                }
            }
            coSame(array(), $named,
                $name . ' builds every URL on the configured origin, and names no host');
        }
    },

    'the accounts that name their own site are left as they are' => function () {
        global $coAccounts;
        $configurable = array_keys($coAccounts);
        $configurable[] = 'YggTorrent';
        // The cases above assign to the settings; this one reads the accounts
        // as an install that has configured nothing sees them.
        coConfigure('yggTorrentOrigin', '');
        foreach ($coAccounts as $name => $nfo) {
            coConfigure($nfo[0], '');
        }
        $untouched = 0;
        foreach (glob(__DIR__ . '/../../../plugins/loginmgr/accounts/*.php') as $file) {
            $name = basename($file, '.php');
            require_once($file);
            $account = new ReflectionClass($name . 'Account');
            $url = $account->getProperty('url')->getValue($account->newInstance());
            if (in_array($name, $configurable, true)) {
                coSame('', $url, $name . ' takes its site from configuration, and none is set');
                continue;
            }
            coSame(true, $url !== '' && parse_url($url, PHP_URL_HOST) !== null,
                $name . ' still names its own site');
            $untouched++;
        }
        coSame(20, $untouched, 'the accounts outside this change are untouched');
    },
);

$failures = 0;
foreach ($tests as $name => $callback) {
    try {
        $callback();
        echo "ok - {$name}\n";
    } catch (Throwable $error) {
        $failures++;
        echo "not ok - {$name}\n";
        echo '  ' . get_class($error) . ': ' . $error->getMessage() . "\n";
    }
}
echo count($tests) . ' tests, ' . $failures . " failures\n";
coResetLog();
coRemoveTree($coTemp);
exit($failures === 0 ? 0 : 1);
