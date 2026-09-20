<?php

/**
 * loginmgr stores the username and password of a tracker account so that
 * Snoopy can log in on the user's behalf. Two paths used to hand the stored
 * password straight back to the browser: accountManager::get(), whose output
 * plugins/loginmgr/init.php appends to the javascript of every page load, and
 * accountManager::getInfo(), which plugins/loginmgr/action.php serves as json.
 * Neither needs the password; the settings page only needs to know whether one
 * is set.
 */

require_once(__DIR__ . '/../../../plugins/loginmgr/accounts.php');

class ProbeExposureAccount extends commonAccount
{
    public $url = 'https://tracker.example';
    protected function isOK($client) { return true; }
    protected function login($c, $l, $p, &$u, &$m, &$ct, &$b, &$f) { return false; }
}

function expAssertTrue($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true)
        );
    }
}

function expManager($password)
{
    $manager = new accountManager();
    $manager->accounts = array(
        'tracker.example' => array(
            'login' => 'someuser',
            'password' => $password,
            'enabled' => 1,
            'auto' => 0,
            'path' => __DIR__ . '/../../../plugins/loginmgr/accounts.php',
            'object' => 'ProbeExposureAccount',
        ),
    );
    return $manager;
}

$secret = 'correct-horse-battery-staple';

$tests = array(
    'the javascript on every page load carries no password' => function () use ($secret) {
        $javascript = expManager($secret)->get();
        expAssertTrue(
            strpos($javascript, $secret) === false,
            'The stored password was written into the page javascript: ' . $javascript
        );
        expAssertTrue(
            strpos($javascript, '"password_set":1') !== false,
            'The page must still be told that a password is set: ' . $javascript
        );
        expAssertTrue(
            strpos($javascript, '"login":"someuser"') !== false,
            'The login is still shown in the settings page: ' . $javascript
        );
    },
    'an account with no password says so' => function () {
        $javascript = expManager('')->get();
        expAssertTrue(
            strpos($javascript, '"password_set":0') !== false,
            'An empty password must read as unset: ' . $javascript
        );
    },
    'the info answer carries no password' => function () use ($secret) {
        $info = expManager($secret)->getInfo();
        $json = json_encode($info);
        expAssertSame(1, count($info), 'One account was configured');
        expAssertTrue(
            strpos($json, $secret) === false,
            'The stored password was served as json: ' . $json
        );
        expAssertTrue(!array_key_exists('password', $info[0]), 'No password field is served at all');
        expAssertSame(1, $info[0]['password_set'], 'The answer still says a password is set');
        expAssertSame('someuser', $info[0]['login'], 'The login is still served');
        expAssertSame('https://tracker.example', $info[0]['url'], 'The account url is still served');
    },
    // What is stored is untouched: this is about what leaves the server, not
    // about forgetting the password.
    'the stored account still holds its password' => function () use ($secret) {
        $manager = expManager($secret);
        $manager->get();
        $manager->getInfo();
        expAssertSame(
            $secret,
            $manager->accounts['tracker.example']['password'],
            'The password Snoopy logs in with must still be there'
        );
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
exit($failures === 0 ? 0 : 1);
