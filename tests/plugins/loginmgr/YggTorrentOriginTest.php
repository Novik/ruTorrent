<?php

/**
 * YggTorrentAccount posts the account's login and password to a host, and
 * which host used to be read off the URL in hand: for the automatic refresh
 * that was commonAccount's placeholder, http://abstract.com, and for a
 * download it was the host of whatever URL had selected the account -- any
 * https host of the shape ygg*.<tld>, which anyone can register. Now the
 * account has one origin, $yggTorrentOrigin from plugins/loginmgr/conf.php:
 * a URL is claimed only on that host, the credentials are posted only there,
 * and without it the account claims nothing, posts nothing, and says so once.
 *
 * The real accounts.php and YggTorrent.php are loaded; the client is a double
 * that records what Snoopy would have been told to send.
 */

// The log and the session cache stay out of the shared /tmp and profile.
$yggTemp = sys_get_temp_dir() . '/ygg-origin-' . getmypid();
mkdir($yggTemp, 0700);
$_ENV['RU_LOG_FILE'] = $yggTemp . '/application.log';
$_ENV['RU_PROFILE_PATH'] = $yggTemp . '/share';

// Every entry point loads php/util.php, and with it conf/config.php, at file
// scope. On the download path accounts.php is then first included from inside
// a method, Snoopy::fetchComplex(), and the plugin configuration it evals
// lands in that method's scope unless accounts.php says otherwise -- so the
// first include here is made from a function too.
require_once(__DIR__ . '/../../../php/util.php');
function yggIncludeFromMethodScope()
{
    require_once(__DIR__ . '/../../../plugins/loginmgr/accounts.php');
    require_once(__DIR__ . '/../../../plugins/loginmgr/accounts/YggTorrent.php');
}
yggIncludeFromMethodScope();

function yggSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true)
        );
    }
}

// Records what the account asks it to send: array(url, method, body).
class YggProbeClient
{
    public $status = 200;
    public $results = 'ok';
    public $cookies = array();
    public $referer = '';
    public $requests = array();

    public function fetch($url, $method = 'GET', $contentType = '', $body = '')
    {
        $this->requests[] = array($url, $method, $body);
        return true;
    }

    public function setcookies()
    {
        $this->cookies['ygg_'] = 'session';
    }
}

// A privateData that never touches rCache.
class YggProbeData
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

// The real account, with its session cache replaced and its name kept, so the
// log line and the once-per-account latch read as they do in production.
class YggProbeAccount extends YggTorrentAccount
{
    public $data;

    public function __construct()
    {
        // The account had no constructor before it read its origin from
        // configuration; without this the failures on that code read as an
        // API difference instead of as where the credentials went.
        if (method_exists('YggTorrentAccount', '__construct')) {
            parent::__construct();
        }
        $this->data = new YggProbeData();
    }

    public function getName()
    {
        return 'YggTorrent';
    }

    protected function loadData($client = null)
    {
        return $this->data;
    }
}

// The base class with no site named, as the old placeholder stood in for.
class YggProbeSitelessAccount extends commonAccount
{
    public $logins = 0;

    protected function isOK($client)
    {
        return true;
    }

    protected function login($c, $l, $p, &$u, &$m, &$ct, &$b, &$f)
    {
        $this->logins++;
        return true;
    }
}

function yggRemoveTree($path)
{
    foreach (scandir($path) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $child = $path . '/' . $entry;
        if (is_dir($child) && !is_link($child)) {
            yggRemoveTree($child);
        } else {
            unlink($child);
        }
    }
    rmdir($path);
}

function yggConfigure($origin)
{
    global $yggTorrentOrigin;
    $yggTorrentOrigin = $origin;
}

function yggLog()
{
    global $log_file;
    return is_file($log_file) ? file_get_contents($log_file) : '';
}

// The refusal is logged once per account and process; a test that reads the
// log starts from a clean latch and an empty log.
function yggResetLog()
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

function yggPosts($client)
{
    $posts = array();
    foreach ($client->requests as $request) {
        if ($request[1] === 'POST') {
            $posts[] = $request;
        }
    }
    return $posts;
}

$tests = array(
    'the setting reaches the account when accounts.php is first included from a method' => function () {
        yggSame(true, array_key_exists('yggTorrentOrigin', $GLOBALS),
            'the plugin configuration must be read into the global scope');
    },

    'the shipped default names no origin' => function () {
        yggSame('', $GLOBALS['yggTorrentOrigin'], 'plugins/loginmgr/conf.php leaves the origin to the user');
        yggSame('', (new YggTorrentAccount())->url, 'and so the account has no site');
    },

    // ---- the automatic refresh (update.php -> checkAuto() -> check()) -------

    'the refresh posts the credentials to the configured origin' => function () {
        yggConfigure('https://yggtorrent.example');
        $account = new YggProbeAccount();
        $client = new YggProbeClient();
        $account->check($client, 'fake-user', 'fake-pass', 0);
        yggSame(array('https://yggtorrent.example', 'GET', ''), $client->requests[0],
            'the landing page is fetched from the configured origin');
        yggSame(array('https://yggtorrent.example/user/login', 'POST', 'id=fake-user&pass=fake-pass&submit='),
            $client->requests[1], 'the credentials are posted to the configured origin');
        yggSame(2, count($client->requests), 'and nothing else is requested');
        foreach ($client->requests as $request) {
            yggSame(false, strpos($request[0], 'abstract.com'), 'no request names the old placeholder');
        }
        yggSame(1, $account->data->stored, 'the renewed session is cached');
    },

    'the refresh of an unconfigured account sends nothing, and says so once' => function () {
        yggConfigure('');
        yggResetLog();
        $account = new YggProbeAccount();
        $client = new YggProbeClient();
        $account->check($client, 'fake-user', 'fake-pass', 0);
        $account->check($client, 'fake-user', 'fake-pass', 0);
        (new YggProbeAccount())->check($client, 'fake-user', 'fake-pass', 0);
        yggSame(array(), $client->requests, 'no request leaves without an origin');
        yggSame(0, $account->data->stored + $account->data->removed, 'the session cache is left alone');
        $log = yggLog();
        yggSame(1, substr_count($log, "\n"), 'one log line for the account, not one per call: ' . $log);
        foreach (array('loginmgr: YggTorrent is not used', '$yggTorrentOrigin is not set', 'plugins/loginmgr/conf.local.php') as $part) {
            yggSame(true, strpos($log, $part) !== false, 'the log line names ' . $part . ': ' . $log);
        }
    },

    'the base class with no site never logs in, and says so once' => function () {
        yggResetLog();
        $account = new YggProbeSitelessAccount();
        yggSame('', $account->url, 'the base class names no site');
        yggSame(false, $account->test('https://abstract.com/x'), 'and claims no URL');
        $account->check(new YggProbeClient(), 'fake-user', 'fake-pass', 0);
        $account->check(new YggProbeClient(), 'fake-user', 'fake-pass', 0);
        yggSame(0, $account->logins, 'no credential flow starts without a site');
        $log = yggLog();
        yggSame(1, substr_count($log, "\n"), 'one log line: ' . $log);
        yggSame(true, strpos($log, 'loginmgr: YggProbeSiteless is not used: the account names no site') !== false,
            'the log line names the account: ' . $log);
    },

    // ---- selection by a URL (getAccount() -> test()) and the download -------

    'a download link on a look-alike host neither selects the account nor gets its credentials' => function () {
        yggConfigure('https://yggtorrent.example');
        $account = new YggProbeAccount();
        $url = 'https://ygg-lookalike.example/engine/download_torrent?id=1';
        yggSame(false, $account->test($url), 'a host of the tracker\'s shape is not the tracker\'s host');
        // Direct use, as if the account had been selected anyway: the login
        // checks the URL for itself.
        $client = new YggProbeClient();
        $client->results = "S'identifier</a>";
        yggSame(false, $account->fetch($client, $url, 'fake-user', 'fake-pass', 'GET', '', ''),
            'the download is refused');
        yggSame(array(), $client->requests, 'and no request, let alone a credential POST, leaves for that host');
    },

    'a download link is claimed on the configured host only' => function () {
        yggConfigure('https://yggtorrent.example');
        $account = new YggProbeAccount();
        $cases = array(
            'https://yggtorrent.example/engine/download_torrent?id=1' => true,
            'https://YGGTORRENT.EXAMPLE./engine/download_torrent?id=1&x=2' => true,
            'https://yggtorrent.example:443/engine/download_torrent?id=1' => true,
            'https://yggtorrent.example/engine/download_torrent?x=2&id=1' => true,
            'https://ygg-lookalike.example/engine/download_torrent?id=1' => false,
            'https://yggtorrent.example@evil.test/engine/download_torrent?id=1' => false,
            'https://yggtorrent.example.evil.test/engine/download_torrent?id=1' => false,
            'https://evil.test/x/yggtorrent.example/engine/download_torrent?id=1' => false,
            'https://cdn.yggtorrent.example/engine/download_torrent?id=1' => false,
            'http://yggtorrent.example/engine/download_torrent?id=1' => false,
            'https://yggtorrent.example:8443/engine/download_torrent?id=1' => false,
            'https://yggtorrent.example/engine/search/?name=x&id=1' => false,
            'https://yggtorrent.example/engine/download_torrent?ref=1' => false,
            'https://yggtorrent.example/engine/download_torrent?xid=1' => false,
            '' => false,
            '/engine/download_torrent?id=1' => false,
        );
        foreach ($cases as $url => $expected) {
            yggSame($expected, $account->test($url), 'test(' . var_export($url, true) . ')');
        }
    },

    'a download on the configured host logs in there' => function () {
        yggConfigure('https://yggtorrent.example');
        $account = new YggProbeAccount();
        $url = 'https://yggtorrent.example/engine/download_torrent?id=7';
        $client = new YggProbeClient();
        yggSame(true, $account->fetch($client, $url, 'fake-user', 'fake-pass', 'GET', '', ''), 'the download succeeds');
        yggSame(array(
            array($url, 'GET', ''),
            array('https://yggtorrent.example/user/login', 'POST', 'id=fake-user&pass=fake-pass&submit='),
            array($url, 'GET', ''),
        ), $client->requests, 'landing page, credential POST, then the download');
        yggSame(1, $account->data->stored, 'the session is cached');
    },

    'an unconfigured account claims no download link, and says so once' => function () {
        yggConfigure('');
        yggResetLog();
        $account = new YggProbeAccount();
        yggSame(false, $account->test('https://yggtorrent.example/engine/download_torrent?id=1'), 'nothing is claimed');
        yggSame(false, $account->test('https://ygg-lookalike.example/engine/download_torrent?id=1'), 'nothing at all');
        yggSame(true, strpos(yggLog(), '$yggTorrentOrigin is not set') !== false, 'a link of the tracker\'s shape is explained');
        yggSame(1, substr_count(yggLog(), "\n"), 'once');
        yggResetLog();
        yggSame(false, $account->test('https://feed.example/rss'), 'an unrelated URL is not claimed');
        yggSame(false, $account->test('https://yggtorrent.example/engine/search/?name=x'), 'nor a page of another shape');
        yggSame('', yggLog(), 'and neither is worth a log line');
    },

    // ---- the configured value ----------------------------------------------

    'a configured origin is folded and keeps its port' => function () {
        foreach (array(
            'https://yggtorrent.example' => 'https://yggtorrent.example',
            'https://yggtorrent.example/' => 'https://yggtorrent.example',
            'HTTPS://YggTorrent.Example.:8443/' => 'https://yggtorrent.example:8443',
            'https://ww4.yggtorrent.example:443' => 'https://ww4.yggtorrent.example:443',
        ) as $configured => $origin) {
            yggConfigure($configured);
            $account = new YggProbeAccount();
            yggSame($origin, $account->url, 'origin of ' . var_export($configured, true));
            $client = new YggProbeClient();
            $account->check($client, 'fake-user', 'fake-pass', 0);
            yggSame($origin . '/user/login', $client->requests[1][0], 'the credential POST goes to that origin');
            yggSame(true, $account->test($origin . '/engine/download_torrent?id=1'), 'a download link there is claimed');
        }
    },

    'a value that is not an https origin disables the account' => function () {
        foreach (array(
            'http://yggtorrent.example', 'yggtorrent.example', 'https://user:pass@yggtorrent.example',
            'https://user@yggtorrent.example', 'https://yggtorrent.example/engine', 'https://yggtorrent.example?x=1',
            'https://yggtorrent.example#x', 'https://ygg torrent.example', "https://yggtorrent.example\n",
            'https://ygg..example', 'https://-ygg.example', 'https://ygg_torrent.example', 'https://yggtorrent.example:0',
            'https://[::1]', ' https://yggtorrent.example', array('https://yggtorrent.example'), null, 0,
        ) as $configured) {
            yggConfigure($configured);
            $account = new YggProbeAccount();
            yggSame('', $account->url, 'origin of ' . var_export($configured, true));
            $client = new YggProbeClient();
            $account->check($client, 'fake-user', 'fake-pass', 0);
            yggSame(false, $account->test('https://yggtorrent.example/engine/download_torrent?id=1'),
                'nothing is claimed with ' . var_export($configured, true));
            yggSame(array(), $client->requests, 'nothing is sent with ' . var_export($configured, true));
        }
        yggConfigure('https://yggtorrent.example?x=1');
        yggResetLog();
        (new YggProbeAccount())->check(new YggProbeClient(), 'fake-user', 'fake-pass', 0);
        yggSame(true, strpos(yggLog(), '$yggTorrentOrigin is not an https origin') !== false, 'the reason is classified');
        yggSame(false, strpos(yggLog(), 'x=1'), 'and the value is not quoted, since it may carry a password');
        yggConfigure(array('https://yggtorrent.example'));
        yggResetLog();
        (new YggProbeAccount())->check(new YggProbeClient(), 'fake-user', 'fake-pass', 0);
        yggSame(true, strpos(yggLog(), '$yggTorrentOrigin is not a string') !== false, 'a value of another type has its own reason');
    },

    // ---- the landing request -----------------------------------------------

    'the credentials are posted only after the landing page answered' => function () {
        yggConfigure('https://yggtorrent.example');
        foreach (array(200 => 1, 204 => 1, 302 => 0, 403 => 0, 503 => 0) as $status => $posts) {
            $client = new YggProbeClient();
            $client->status = $status;
            (new YggProbeAccount())->check($client, 'fake-user', 'fake-pass', 0);
            yggSame($posts, count(yggPosts($client)), 'credential POSTs after a landing status of ' . $status);
            yggSame('https://yggtorrent.example', $client->requests[0][0], 'the landing request is made');
        }
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
yggResetLog();
yggRemoveTree($yggTemp);
exit($failures === 0 ? 0 : 1);
