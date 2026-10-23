<?php

$saveFailureBase = sys_get_temp_dir() . '/rutorrent-account-save-' . getmypid() . '-' . bin2hex(random_bytes(4));
mkdir($saveFailureBase . '/profile/settings', 0700, true);
$_ENV['RU_PROFILE_PATH'] = $saveFailureBase . '/profile';
$_ENV['RU_LOG_FILE'] = $saveFailureBase . '/application.log';
require_once(__DIR__ . '/../../../plugins/loginmgr/accounts.php');

function saveAssert($condition, $message)
{
    if (!$condition) throw new RuntimeException($message);
}

function saveRemoveTree($path)
{
    if (!is_dir($path) || is_link($path)) {
        if (file_exists($path) || is_link($path)) unlink($path);
        return;
    }
    foreach (array_diff(scandir($path), array('.', '..')) as $name) saveRemoveTree($path . '/' . $name);
    rmdir($path);
}

// Persistence and session deletion stay real. Observe only the daemon side
// effect and read the durable credentials at the point a session is retired.
class SaveFailureManager extends accountManager
{
    public $handlerCalls = 0;
    public $persistedAtForget = array();

    public function setHandlers() { ++$this->handlerCalls; }

    protected function forgetSession($name)
    {
        $stored = new self();
        saveAssert((new rCache())->get($stored), 'The credential cache is readable when a session is retired');
        $this->persistedAtForget[$name] = $stored->accounts;
        return parent::forgetSession($name);
    }
}

function saveManager($class = 'SaveFailureManager')
{
    $manager = new $class();
    foreach (array('Alpha', 'Beta') as $name) {
        $manager->accounts[$name] = array('name' => $name, 'login' => 'old-' . $name,
            'password' => 'saved-value', 'enabled' => 1, 'auto' => 0,
            'path' => __FILE__, 'object' => 'UnusedAccount');
    }
    return $manager;
}

function saveSeed()
{
    $directory = FileUtil::getSettingsPath();
    saveRemoveTree($directory);
    mkdir($directory, 0700);
    $manager = saveManager();
    saveAssert($manager->store() === true, 'The initial credentials are saved through the real cache');
    $sessions = array();
    foreach (array_keys($manager->accounts) as $name) {
        $session = new privateData($name);
        $session->cookies = array('sid' => 'session-' . $name);
        saveAssert((new rCache('/accounts'))->set($session) === true, 'The initial session is saved');
        $sessions[$name] = file_get_contents($directory . '/accounts/' . $name . '.dat');
    }
    return array($manager, file_get_contents($directory . '/loginmgr.dat'), $sessions);
}

function saveBlockWrite()
{
    $lock = FileUtil::getSettingsPath() . '/loginmgr.dat.lock';
    saveAssert(unlink($lock) && mkdir($lock, 0700), 'A directory blocks the real credential-cache lock file');
}

function saveAction($blocked, $check)
{
    global $saveFailureBase;
    $root = dirname(__DIR__, 3);
    $directory = $saveFailureBase . '/http';
    mkdir($directory . '/profile/settings/accounts', 0700, true);
    $manager = saveManager('accountManager');
    // The fixture starts with an existing account cache; the action itself
    // loads and writes it with the unmodified production rCache.
    file_put_contents($directory . '/profile/settings/loginmgr.dat', serialize($manager));
    foreach (array_keys($manager->accounts) as $name) {
        $session = new privateData($name);
        $session->cookies = array('sid' => 'session-' . $name);
        file_put_contents($directory . '/profile/settings/accounts/' . $name . '.dat', serialize($session));
    }
    if ($blocked) mkdir($directory . '/profile/settings/loginmgr.dat.lock', 0700);
    $router = '<?php $_ENV["RU_PROFILE_PATH"] = ' . var_export($directory . '/profile', true)
        . '; $_ENV["RU_LOG_FILE"] = ' . var_export($directory . '/application.log', true)
        . '; require ' . var_export($root . '/plugins/loginmgr/accounts.php', true) . ';'
        . ' Closure::bind(function() { rTorrentSettings::$theSettings = '
        . '(new ReflectionClass("rTorrentSettings"))->newInstanceWithoutConstructor(); }, null, "rTorrentSettings")();'
        . ' require ' . var_export($root . '/plugins/loginmgr/action.php', true) . ';';
    file_put_contents($directory . '/router.php', $router);
    $listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    saveAssert($listener !== false, 'The HTTP fixture reserves an address: ' . $error);
    $address = stream_socket_get_name($listener, false);
    fclose($listener);
    $process = proc_open(array(PHP_BINARY, '-c', $root . '/tests/php-test.ini', '-S', $address,
        $directory . '/router.php'), array(0 => array('file', '/dev/null', 'r'),
        1 => array('file', $directory . '/server.log', 'a'),
        2 => array('file', $directory . '/server.log', 'a')), $pipes, $root . '/plugins/loginmgr');
    saveAssert(is_resource($process), 'The HTTP fixture starts without a shell');
    try {
        $ready = false;
        for ($attempt = 0; $attempt < 100; ++$attempt) {
            $peer = @stream_socket_client('tcp://' . $address, $errno, $error, 0.05);
            if ($peer !== false) { fclose($peer); $ready = true; break; }
            if (!proc_get_status($process)['running']) break;
            usleep(10000);
        }
        saveAssert($ready, 'The HTTP fixture is ready: ' . file_get_contents($directory . '/server.log'));
        $peer = stream_socket_client('tcp://' . $address, $errno, $error, 3);
        saveAssert($peer !== false, 'The settings request connects');
        try {
            stream_set_timeout($peer, 3);
            $form = http_build_query(array('mode' => 'set', 'Alpha_login' => 'new-user'));
            fwrite($peer, "POST / HTTP/1.0\r\nHost: localhost\r\nContent-Type: application/x-www-form-urlencoded\r\n"
                . 'Content-Length: ' . strlen($form) . "\r\nConnection: close\r\n\r\n" . $form);
            $response = stream_get_contents($peer);
        } finally {
            fclose($peer);
        }
        saveAssert(is_string($response) && strpos($response, "\r\n\r\n") !== false, 'The action sends a complete response');
        list($head, $body) = explode("\r\n\r\n", $response, 2);
        $check($head, $body, $directory . '/profile/settings');
    } finally {
        proc_terminate($process);
        proc_close($process);
        saveRemoveTree($directory);
    }
}

$tests = array(
    'a refused cache write retains the saved credentials and sessions' => function () {
        list($manager, $stored, $sessions) = saveSeed();
        saveBlockWrite();
        $_REQUEST = array('Alpha_login' => 'new-user');
        // Failure to open this deliberately blocked cache lock is expected.
        @$manager->set();
        $directory = FileUtil::getSettingsPath();
        saveAssert(file_get_contents($directory . '/loginmgr.dat') === $stored, 'The prior credential bytes survive');
        foreach ($sessions as $name => $bytes) {
            $path = $directory . '/accounts/' . $name . '.dat';
            saveAssert(is_file($path) && file_get_contents($path) === $bytes, 'The prior session survives for ' . $name);
        }
        saveAssert($manager->handlerCalls === 0 && $manager->persistedAtForget === array(), 'No session or schedule side effect runs');
    },
    'a refused write restores the live manager and can be retried' => function () {
        list($manager) = saveSeed();
        $previous = $manager->accounts;
        saveBlockWrite();
        $_REQUEST = array('Alpha_login' => 'new-user');
        $saved = @$manager->set();
        saveAssert($manager->accounts === $previous, 'The same object retains its prior account values');
        saveAssert($saved === false, 'A refused write returns false');
        rmdir(FileUtil::getSettingsPath() . '/loginmgr.dat.lock');
        saveAssert($manager->set() === true, 'The same manager retries after the cache is repaired');
        saveAssert($manager->accounts['Alpha']['login'] === 'new-user', 'The retried edit is applied');
        saveAssert($manager->handlerCalls === 1, 'Only the successful attempt updates handlers');
    },
    'a successful save commits before retiring all old sessions' => function () {
        list($manager) = saveSeed();
        $_REQUEST = array('Alpha_login' => 'new-user');
        $saved = $manager->set();
        $expected = $manager->accounts;
        saveAssert($manager->persistedAtForget === array('Alpha' => $expected, 'Beta' => $expected),
            'Every session is retired only after the complete new credentials are readable');
        saveAssert($saved === true && $manager->handlerCalls === 1, 'Success is explicit and updates handlers once');
        saveAssert($expected['Alpha']['login'] === 'new-user' && $expected['Alpha']['password'] === 'saved-value'
            && $expected['Beta']['login'] === 'old-Beta', 'Omitted values and other accounts keep their prior values');
        foreach (array_keys($expected) as $name) {
            saveAssert(!file_exists(FileUtil::getSettingsPath() . '/accounts/' . $name . '.dat'), 'The old session is retired for ' . $name);
        }
    },
    'the action reports a real cache failure instead of a success assignment' => function () {
        saveAction(true, function ($head, $body, $directory) {
            saveAssert(strpos($head, ' 500 ') !== false, 'The action reports HTTP 500');
            saveAssert(preg_match('/^Content-Type: text\/plain; charset=UTF-8\r?$/mi', $head) === 1, 'The failure is plain text');
            saveAssert($body === 'Account settings could not be saved.', 'The failure is not a settings-success assignment');
            $stored = unserialize(file_get_contents($directory . '/loginmgr.dat'));
            saveAssert($stored->accounts['Alpha']['login'] === 'old-Alpha', 'The persisted account remains the old one');
            saveAssert(is_file($directory . '/accounts/Alpha.dat'), 'The action keeps the previous session');
        });
    },
    'the action still returns saved settings after a successful write' => function () {
        saveAction(false, function ($head, $body, $directory) {
            saveAssert(strpos($head, ' 200 ') !== false, 'Successful settings still return HTTP 200');
            $prefix = 'theWebUI.theAccounts = ';
            saveAssert(strpos($body, $prefix) === 0 && substr($body, -2) === ";\n", 'The success assignment keeps its shape');
            $accounts = json_decode(substr($body, strlen($prefix), -2), true);
            saveAssert(is_array($accounts) && $accounts['Alpha']['login'] === 'new-user', 'The response contains the saved edit');
            $stored = unserialize(file_get_contents($directory . '/loginmgr.dat'));
            saveAssert($stored->accounts['Alpha']['login'] === 'new-user', 'The response agrees with the durable account');
            saveAssert(!file_exists($directory . '/accounts/Alpha.dat'), 'Success retires the old session');
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
    saveRemoveTree($saveFailureBase);
}
echo count($tests), ' tests, ', $failures, " failures\n";
exit($failures ? 1 : 0);
