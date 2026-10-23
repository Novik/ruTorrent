<?php

/**
 * accountManager::obtain() builds the account list from the accounts
 * directory and writes it, and it is what a profile with no stored list is
 * answered from -- plugins/loginmgr/action.php calls it before it serves any
 * settings request. The write can be refused, and then nothing this request
 * answers with is there for the next one, so the result is the caller's
 * business. The scheduled job belongs on the far side of that write for the
 * same reason as in set(): a schedule registered for a list no later request
 * can read describes accounts that are not there.
 *
 * The refused writes below are suppressed at the call site because what is
 * asserted here is the answer, not the warning: what the cache reports about
 * a write it would not make is the cache's own tests to assert.
 *
 * Reading the directory can fail as well, and that failure is not the same as
 * finding it empty. An enumeration that never happened says nothing about
 * which accounts exist, so it may not be written down as "none": the store
 * that follows succeeds, and an empty list is durable in a way the condition
 * that produced it is not. action.php reaches obtain() whenever the stored
 * list cannot be loaded, so what the write lands on may be a list with
 * credentials in it.
 */

$discoveryBase = sys_get_temp_dir() . '/rutorrent-account-discovery-' . getmypid() . '-' . bin2hex(random_bytes(4));
mkdir($discoveryBase . '/profile/settings', 0700, true);
$_ENV['RU_PROFILE_PATH'] = $discoveryBase . '/profile';
$_ENV['RU_LOG_FILE'] = $discoveryBase . '/application.log';
require_once(__DIR__ . '/../../../plugins/loginmgr/accounts.php');

function discoveryAssert($condition, $message)
{
    if (!$condition) throw new RuntimeException($message);
}

function discoveryRemoveTree($path)
{
    if (!is_dir($path) || is_link($path)) {
        if (file_exists($path) || is_link($path)) unlink($path);
        return;
    }
    foreach (array_diff(scandir($path), array('.', '..')) as $name) discoveryRemoveTree($path . '/' . $name);
    rmdir($path);
}

// Persistence stays real. Only the scheduled job is counted instead of
// registered, which needs a running daemon.
class DiscoveryManager extends accountManager
{
    public $handlerCalls = 0;

    public function setHandlers() { ++$this->handlerCalls; }
}

// obtain() reads names out of a directory and never loads the files, so two
// empty ones are a complete accounts directory for this.
function discoverySeed()
{
    global $discoveryBase;
    $settings = FileUtil::getSettingsPath();
    discoveryRemoveTree($settings);
    mkdir($settings, 0700);
    $accounts = $discoveryBase . '/available';
    discoveryRemoveTree($accounts);
    mkdir($accounts, 0700);
    foreach (array('Alpha', 'Beta') as $name) touch($accounts . '/' . $name . '.php');
    return $accounts;
}

// A profile holding a discovered list with credentials filled in, as one is
// after the settings page has been used once.
function discoveryStoreCredentials()
{
    $manager = new DiscoveryManager();
    discoveryAssert($manager->obtain(discoverySeed()) === true, 'The discovered list is stored');
    $manager->accounts['Alpha']['login'] = 'alpha-login';
    $manager->accounts['Alpha']['password'] = 'alpha-password';
    $manager->accounts['Alpha']['enabled'] = 1;
    $manager->accounts['Alpha']['auto'] = 3600;
    discoveryAssert($manager->store() === true, 'The filled-in credentials are stored');
}

// What the next request would load.
function discoveryStored()
{
    $stored = new DiscoveryManager();
    discoveryAssert((new rCache())->get($stored), 'The stored account list is readable');
    return $stored->accounts;
}

// A directory opendir() refuses. Being refused takes a uid that can be
// refused: root reads a 0000 directory, and there the absent path below is
// the one shape of the same failure left. The caller restores the mode.
function discoveryUnreadableDir()
{
    global $discoveryBase;
    $locked = $discoveryBase . '/locked';
    discoveryRemoveTree($locked);
    mkdir($locked, 0700);
    touch($locked . '/Gamma.php');
    chmod($locked, 0000);
    $handle = @opendir($locked);
    if ($handle === false) return $locked;
    closedir($handle);
    chmod($locked, 0700);
    return $discoveryBase . '/absent';
}

// obtain() against a directory it cannot enumerate, with the credentials the
// profile held before it recorded for comparison.
function discoveryFailedEnumeration($dir)
{
    global $discoveryBase;
    discoveryStoreCredentials();
    $before = discoveryStored();
    discoveryAssert($before['Alpha']['login'] === 'alpha-login' && $before['Alpha']['enabled'] === 1,
        'The profile holds configured credentials before the discovery that fails');

    $manager = new DiscoveryManager();
    $manager->accounts = $before;
    // A handler is called for a diagnostic the call site suppressed as well,
    // and error_reporting() is what says which those were. What is asserted
    // is that nothing reportable is raised: obtain() runs while action.php is
    // composing a response, and a warning there is printed into it.
    $diagnostics = array();
    set_error_handler(function ($number, $message) use (&$diagnostics) {
        if (error_reporting() & $number) $diagnostics[] = $message;
        return true;
    });
    ob_start();
    try {
        $written = $manager->obtain($dir);
    } finally {
        $printed = ob_get_clean();
        restore_error_handler();
    }

    discoveryAssert($written === false, 'A discovery that could not read the directory answers false');
    discoveryAssert($diagnostics === array(),
        'and raises no PHP diagnostic into the answer being composed: ' . implode('; ', $diagnostics));
    discoveryAssert($printed === '', 'and prints nothing into it: ' . $printed);
    discoveryAssert(discoveryStored() === $before, 'The stored credentials are the ones that were there');
    discoveryAssert($manager->accounts === $before,
        'and the list this request answers from is the one it had');
    discoveryAssert($manager->handlerCalls === 0,
        'No schedule is registered for a list that was never discovered');
    discoveryAssert(strpos(file_get_contents($discoveryBase . '/application.log'),
        'the accounts directory could not be read') !== false, 'The refusal is logged');
}

function discoveryBlockWrite()
{
    $lock = FileUtil::getSettingsPath() . '/loginmgr.dat.lock';
    discoveryAssert(mkdir($lock, 0700), 'A directory blocks the real credential-cache lock file');
}

// A settings request against the real action, on a profile that holds no
// account list and cannot be written.
function discoveryAction($blocked, $check)
{
    global $discoveryBase;
    $root = dirname(__DIR__, 3);
    $directory = $discoveryBase . '/http';
    $settings = $directory . '/profile/settings';
    mkdir($settings . '/accounts', 0700, true);
    if ($blocked) mkdir($settings . '/loginmgr.dat.lock', 0700);
    $router = '<?php $_ENV["RU_PROFILE_PATH"] = ' . var_export($directory . '/profile', true)
        . '; $_ENV["RU_LOG_FILE"] = ' . var_export($directory . '/application.log', true)
        . '; require ' . var_export($root . '/plugins/loginmgr/accounts.php', true) . ';'
        . ' Closure::bind(function() { rTorrentSettings::$theSettings = '
        . '(new ReflectionClass("rTorrentSettings"))->newInstanceWithoutConstructor(); }, null, "rTorrentSettings")();'
        . ' require ' . var_export($root . '/plugins/loginmgr/action.php', true) . ';';
    file_put_contents($directory . '/router.php', $router);
    $listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    discoveryAssert($listener !== false, 'The HTTP fixture reserves an address: ' . $error);
    $address = stream_socket_get_name($listener, false);
    fclose($listener);
    $process = proc_open(array(PHP_BINARY, '-c', $root . '/tests/php-test.ini', '-S', $address,
        $directory . '/router.php'), array(0 => array('file', '/dev/null', 'r'),
        1 => array('file', $directory . '/server.log', 'a'),
        2 => array('file', $directory . '/server.log', 'a')), $pipes, $root . '/plugins/loginmgr');
    discoveryAssert(is_resource($process), 'The HTTP fixture starts without a shell');
    try {
        $ready = false;
        for ($attempt = 0; $attempt < 100; ++$attempt) {
            $peer = @stream_socket_client('tcp://' . $address, $errno, $error, 0.05);
            if ($peer !== false) { fclose($peer); $ready = true; break; }
            if (!proc_get_status($process)['running']) break;
            usleep(10000);
        }
        discoveryAssert($ready, 'The HTTP fixture is ready: ' . file_get_contents($directory . '/server.log'));
        $peer = stream_socket_client('tcp://' . $address, $errno, $error, 3);
        discoveryAssert($peer !== false, 'The settings request connects');
        try {
            stream_set_timeout($peer, 3);
            $form = http_build_query(array('mode' => 'info'));
            fwrite($peer, "POST / HTTP/1.0\r\nHost: localhost\r\nContent-Type: application/x-www-form-urlencoded\r\n"
                . 'Content-Length: ' . strlen($form) . "\r\nConnection: close\r\n\r\n" . $form);
            $response = stream_get_contents($peer);
        } finally {
            fclose($peer);
        }
        discoveryAssert(is_string($response) && strpos($response, "\r\n\r\n") !== false,
            'The action sends a complete response');
        list($head, $body) = explode("\r\n\r\n", $response, 2);
        $check($head, $body, $settings);
    } finally {
        proc_terminate($process);
        proc_close($process);
        discoveryRemoveTree($directory);
    }
}

$tests = array(
    'a discovery write that was refused is not reported as success' => function () {
        global $discoveryBase;
        $accounts = discoverySeed();
        discoveryBlockWrite();
        $manager = new DiscoveryManager();
        // The refusal is the point; the warning it carries is not.
        $written = @$manager->obtain($accounts);
        discoveryAssert($written === false, 'A refused discovery write answers false');
        discoveryAssert(!is_file(FileUtil::getSettingsPath() . '/loginmgr.dat'),
            'Nothing durable was left behind');
        discoveryAssert($manager->handlerCalls === 0,
            'No schedule is registered for a list the next request cannot read');
        discoveryAssert(strpos(file_get_contents($discoveryBase . '/application.log'),
            'the discovered account list could not be saved') !== false, 'The refusal is logged');
        discoveryAssert(array_keys($manager->accounts) === array('Alpha', 'Beta'),
            'The list this request answers from is still the one that was discovered');
    },
    'an accounts directory that cannot be read is not a discovery of nothing' => function () {
        $locked = discoveryUnreadableDir();
        try {
            discoveryFailedEnumeration($locked);
        } finally {
            @chmod($locked, 0700);
        }
    },
    'an accounts directory that is not there is not a discovery of nothing' => function () {
        global $discoveryBase;
        discoveryFailedEnumeration($discoveryBase . '/absent');
    },
    'a discovery write that was made reports success' => function () {
        $accounts = discoverySeed();
        $manager = new DiscoveryManager();
        discoveryAssert($manager->obtain($accounts) === true, 'A discovery that was written answers true');
        discoveryAssert($manager->handlerCalls === 1, 'The schedule is registered once');
        $stored = new DiscoveryManager();
        discoveryAssert((new rCache())->get($stored) && array_keys($stored->accounts) === array('Alpha', 'Beta'),
            'The durable list is the one that was discovered');
    },
    'the action reports a discovery it could not write' => function () {
        discoveryAction(true, function ($head, $body, $settings) {
            discoveryAssert(strpos($head, ' 500 ') !== false, 'The action reports HTTP 500');
            discoveryAssert(preg_match('/^Content-Type: text\/plain; charset=UTF-8\r?$/mi', $head) === 1,
                'The report is plain text');
            discoveryAssert($body === 'Account settings could not be read.',
                'The report says the list is unreadable rather than answering with it');
            discoveryAssert(!is_file($settings . '/loginmgr.dat'), 'Nothing durable was left behind');
        });
    },
    'the action answers a discovery it could write' => function () {
        discoveryAction(false, function ($head, $body, $settings) {
            discoveryAssert(strpos($head, ' 200 ') !== false, 'A written discovery still answers HTTP 200');
            discoveryAssert(is_array(json_decode($body, true)), 'The answer is the json account list');
            discoveryAssert(is_file($settings . '/loginmgr.dat'), 'The discovered list is durable');
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
        }
    }
} finally {
    discoveryRemoveTree($discoveryBase);
}
echo count($tests), ' tests, ', $failures, " failures\n";
exit($failures ? 1 : 0);
