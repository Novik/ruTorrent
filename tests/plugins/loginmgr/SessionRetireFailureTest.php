<?php

/**
 * accountManager::set() discards every stored session once it has committed
 * new credentials, because a session made with the credentials that were just
 * replaced is still accepted by the tracker.
 *
 * The cache can refuse that removal: it needs the writer lock a store takes,
 * and the unlink can fail on its own. The session is then still on disk and
 * still handed to the next client that asks for it, and because the save
 * itself succeeded the return value of set() cannot say so. What a caller
 * observes is asserted here -- the cookies privateData::load() injects -- not
 * only what the removal returned.
 */

$retireBase = sys_get_temp_dir() . '/rutorrent-session-retire-' . getmypid() . '-' . bin2hex(random_bytes(4));
mkdir($retireBase . '/profile/settings', 0700, true);
$_ENV['RU_PROFILE_PATH'] = $retireBase . '/profile';
$_ENV['RU_LOG_FILE'] = $retireBase . '/application.log';
require_once(__DIR__ . '/../../../plugins/loginmgr/accounts.php');

function retireAssert($condition, $message)
{
    if (!$condition) throw new RuntimeException($message);
}

function retireRemoveTree($path)
{
    if (!is_dir($path) || is_link($path)) {
        if (file_exists($path) || is_link($path)) unlink($path);
        return;
    }
    foreach (array_diff(scandir($path), array('.', '..')) as $name) retireRemoveTree($path . '/' . $name);
    rmdir($path);
}

// All privateData::load() wants of a client is the two fields it fills, and
// what it puts there is what the next fetch() sends to the tracker.
class RetireClient
{
    public $cookies = null;
    public $referer = null;
}

// Persistence and session removal stay real. Only the scheduled job is
// counted instead of registered, which needs a running daemon.
class RetireManager extends accountManager
{
    public $handlerCalls = 0;

    public function setHandlers() { ++$this->handlerCalls; }
}

function retireManager($class = 'RetireManager')
{
    $manager = new $class();
    foreach (array('Alpha', 'Beta') as $name) {
        $manager->accounts[$name] = array('name' => $name, 'login' => 'old-' . $name,
            'password' => 'saved-value', 'enabled' => 1, 'auto' => 0,
            'path' => __FILE__, 'object' => 'UnusedAccount');
    }
    return $manager;
}

// Credentials, and one stored session per account. Storing a session writes a
// lock sidecar beside it, so a later removal reaches its unlink rather than
// stopping at a lock file it would first have had to create.
function retireSeed()
{
    $directory = FileUtil::getSettingsPath();
    retireRemoveTree($directory);
    mkdir($directory, 0700);
    $manager = retireManager();
    retireAssert($manager->store() === true, 'The credentials are saved through the real cache');
    foreach (array_keys($manager->accounts) as $name) {
        $session = new privateData($name);
        $session->cookies = array('sid' => 'session-' . $name);
        retireAssert((new rCache('/accounts'))->set($session) === true, 'The session is saved for ' . $name);
        retireAssert(is_file($directory . '/accounts/' . $name . '.dat.lock'),
            'The session cache keeps its lock sidecar for ' . $name);
    }
    return $manager;
}

// The cookies a client is handed for this account, or null when none are.
function retireSessionOf($name)
{
    $client = new RetireClient();
    $data = privateData::load($name, $client);
    return $data->loaded ? $client->cookies : null;
}

// A settings request against the real action, with the profile prepared so
// that the credential write succeeds and the session removal cannot.
function retireAction($check)
{
    global $retireBase;
    $root = dirname(__DIR__, 3);
    $directory = $retireBase . '/http';
    $settings = $directory . '/profile/settings';
    mkdir($settings . '/accounts', 0700, true);
    // The action loads and writes this with the unmodified production classes,
    // so the stored root must be the class it reads for, not a test subclass.
    $manager = retireManager('accountManager');
    file_put_contents($settings . '/loginmgr.dat', serialize($manager));
    foreach (array_keys($manager->accounts) as $name) {
        $session = new privateData($name);
        $session->cookies = array('sid' => 'session-' . $name);
        file_put_contents($settings . '/accounts/' . $name . '.dat', serialize($session));
        touch($settings . '/accounts/' . $name . '.dat.lock');
    }
    chmod($settings . '/accounts', 0500);
    $router = '<?php $_ENV["RU_PROFILE_PATH"] = ' . var_export($directory . '/profile', true)
        . '; $_ENV["RU_LOG_FILE"] = ' . var_export($directory . '/application.log', true)
        . '; require ' . var_export($root . '/plugins/loginmgr/accounts.php', true) . ';'
        . ' Closure::bind(function() { rTorrentSettings::$theSettings = '
        . '(new ReflectionClass("rTorrentSettings"))->newInstanceWithoutConstructor(); }, null, "rTorrentSettings")();'
        . ' require ' . var_export($root . '/plugins/loginmgr/action.php', true) . ';';
    file_put_contents($directory . '/router.php', $router);
    $listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    retireAssert($listener !== false, 'The HTTP fixture reserves an address: ' . $error);
    $address = stream_socket_get_name($listener, false);
    fclose($listener);
    $process = proc_open(array(PHP_BINARY, '-c', $root . '/tests/php-test.ini', '-S', $address,
        $directory . '/router.php'), array(0 => array('file', '/dev/null', 'r'),
        1 => array('file', $directory . '/server.log', 'a'),
        2 => array('file', $directory . '/server.log', 'a')), $pipes, $root . '/plugins/loginmgr');
    retireAssert(is_resource($process), 'The HTTP fixture starts without a shell');
    try {
        $ready = false;
        for ($attempt = 0; $attempt < 100; ++$attempt) {
            $peer = @stream_socket_client('tcp://' . $address, $errno, $error, 0.05);
            if ($peer !== false) { fclose($peer); $ready = true; break; }
            if (!proc_get_status($process)['running']) break;
            usleep(10000);
        }
        retireAssert($ready, 'The HTTP fixture is ready: ' . file_get_contents($directory . '/server.log'));
        $peer = stream_socket_client('tcp://' . $address, $errno, $error, 3);
        retireAssert($peer !== false, 'The settings request connects');
        try {
            stream_set_timeout($peer, 3);
            $form = http_build_query(array('mode' => 'set', 'Alpha_login' => 'new-user'));
            fwrite($peer, "POST / HTTP/1.0\r\nHost: localhost\r\nContent-Type: application/x-www-form-urlencoded\r\n"
                . 'Content-Length: ' . strlen($form) . "\r\nConnection: close\r\n\r\n" . $form);
            $response = stream_get_contents($peer);
        } finally {
            fclose($peer);
        }
        retireAssert(is_string($response) && strpos($response, "\r\n\r\n") !== false, 'The action sends a complete response');
        list($head, $body) = explode("\r\n\r\n", $response, 2);
        $check($head, $body, $settings);
    } finally {
        proc_terminate($process);
        proc_close($process);
        chmod($settings . '/accounts', 0700);
        retireRemoveTree($directory);
    }
}

$tests = array(
    'a session the cache will not discard is named to the caller' => function () {
        global $retireBase;
        $manager = retireSeed();
        $directory = FileUtil::getSettingsPath();
        // Only the session directory is unwritable, so the credential write in
        // its parent still commits and the removals below cannot.
        chmod($directory . '/accounts', 0500);
        try {
            $_REQUEST = array('Alpha_login' => 'new-user');
            $notRetired = array();
            $saved = $manager->set($notRetired);
            retireAssert($saved === true, 'The credential write commits');
            $stored = new RetireManager();
            retireAssert((new rCache())->get($stored) && $stored->accounts['Alpha']['login'] === 'new-user',
                'The durable credentials are the new ones');
            retireAssert(retireSessionOf('Alpha') === array('sid' => 'session-Alpha'),
                'A client is still handed the session made with the replaced credentials');
            retireAssert($notRetired === array('Alpha', 'Beta'),
                'Every session that survived the write is named');
            retireAssert(strpos(file_get_contents($retireBase . '/application.log'), 'Alpha, Beta') !== false,
                'The surviving sessions are named in the log');
            retireAssert($manager->handlerCalls === 1, 'The schedule still matches the saved credentials');
        } finally {
            chmod($directory . '/accounts', 0700);
        }
    },
    'a save that discards every session reports none' => function () {
        $manager = retireSeed();
        $_REQUEST = array('Alpha_login' => 'new-user');
        $notRetired = array();
        retireAssert($manager->set($notRetired) === true, 'The save succeeds');
        retireAssert($notRetired === array(), 'Nothing is named when every session was discarded');
        retireAssert(retireSessionOf('Alpha') === null && retireSessionOf('Beta') === null,
            'No session is handed to a client afterwards');
    },
    'privateData::remove answers with what the cache did' => function () {
        retireSeed();
        $directory = FileUtil::getSettingsPath();
        retireAssert((new privateData('Alpha'))->remove() === true, 'A removal the cache made is true');
        chmod($directory . '/accounts', 0500);
        try {
            retireAssert((new privateData('Beta'))->remove() === false, 'A removal the cache refused is false');
            retireAssert(retireSessionOf('Beta') === array('sid' => 'session-Beta'),
                'The refused removal left the session where it was');
        } finally {
            chmod($directory . '/accounts', 0700);
        }
    },
    'the action reports a session it could not discard' => function () {
        retireAction(function ($head, $body, $settings) {
            retireAssert(strpos($head, ' 500 ') !== false, 'The action reports HTTP 500');
            retireAssert(preg_match('/^Content-Type: text\/plain; charset=UTF-8\r?$/mi', $head) === 1,
                'The report is plain text');
            retireAssert($body === 'Account settings were saved, but a previous session could not be discarded.',
                'The report says the session survived rather than assigning the settings');
            $stored = unserialize(file_get_contents($settings . '/loginmgr.dat'));
            retireAssert($stored->accounts['Alpha']['login'] === 'new-user',
                'The saved credentials stand, so the next login can replace the session');
            retireAssert(is_file($settings . '/accounts/Alpha.dat'), 'The session the report names is still there');
        });
    },
);

$failures = 0;
try {
    foreach ($tests as $name => $test) {
        try {
            $test();
            echo 'ok - ', $name, "\n";
        } catch (Throwable $error) {
            ++$failures;
            echo 'not ok - ', $name, ': ', $error->getMessage(), "\n";
        } finally {
            $_REQUEST = array();
        }
    }
} finally {
    retireRemoveTree($retireBase);
}
echo count($tests), ' tests, ', $failures, " failures\n";
exit($failures ? 1 : 0);
