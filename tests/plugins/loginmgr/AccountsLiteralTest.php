<?php

/**
 * accountManager::get() is appended to the javascript of every page load by
 * plugins/loginmgr/init.php, and is the whole answer of the "set" mode in
 * plugins/loginmgr/action.php. It used to be built as javascript text, with
 * each stored value concatenated in as source: a settings request that stored
 * script syntax in one of them had that script run on every later page load
 * for the profile. accountManager::set() is what stores them, and took the
 * request values as they arrived.
 *
 * So there are two halves to hold: set() keeps only a flag, a number or a
 * text, and get() emits one json literal that no stored value can end.
 */

require_once(__DIR__ . '/../../../plugins/loginmgr/accounts.php');

class ProbeLiteralAccount extends commonAccount
{
    public $url = 'https://tracker.example';
    protected function isOK($client) { return true; }
    protected function login($c, $l, $p, &$u, &$m, &$ct, &$b, &$f) { return false; }
}

// set() stores, drops the cached session and re-registers the scheduled job.
// None of that is what is under test here, and all of it wants a settings
// directory and a running daemon.
class ProbeLiteralManager extends accountManager
{
    public $stored = 0;
    public $forgotten = array();
    public function store() { $this->stored++; return true; }
    public function setHandlers() {}
    protected function forgetSession($name) { $this->forgotten[] = $name; }
}

function litAssertTrue($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function litAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true)
        );
    }
}

function litManager($account = 'tracker.example')
{
    $manager = new ProbeLiteralManager();
    $manager->accounts = array(
        $account => array(
            'name' => $account,
            'login' => '',
            'password' => '',
            'enabled' => 0,
            'auto' => 0,
            'path' => __DIR__ . '/../../../plugins/loginmgr/accounts.php',
            'object' => 'ProbeLiteralAccount',
        ),
    );
    return $manager;
}

/**
 * The accounts object the emitted script assigns, or a RuntimeException naming
 * what the script is instead.
 *
 * This is the whole test: the answer has to be the assignment prefix, one json
 * literal, and the statement terminator. A stored value that ended the literal
 * leaves text that is not json, and a stored value that ended the statement
 * leaves text after the terminator.
 */
function litAssignedValue($javascript)
{
    $prefix = 'theWebUI.theAccounts = ';
    litAssertTrue(
        strncmp($javascript, $prefix, strlen($prefix)) === 0,
        'The answer must be that one assignment: ' . var_export($javascript, true)
    );
    litAssertTrue(
        substr($javascript, -2) === ";\n",
        'The assignment must end at its terminator: ' . var_export($javascript, true)
    );
    $literal = substr($javascript, strlen($prefix), -2);
    $value = json_decode($literal, true);
    litAssertTrue(
        json_last_error() === JSON_ERROR_NONE,
        'What is assigned is not one json literal, so a stored value ended it: '
        . var_export($literal, true)
    );
    // The flags JSON::jsValue() carries keep these out of the bytes, so the
    // answer cannot close a string, an attribute or the element holding it.
    foreach (array('<', '>', '&', "'", "\n", "\r") as $forbidden) {
        litAssertTrue(
            strpos($literal, $forbidden) === false,
            'The literal carries a raw ' . var_export($forbidden, true) . ': '
            . var_export($literal, true)
        );
    }
    return $value;
}

// One payload per shape a stored value is written in. Each ends what the old
// code had opened -- the object, the quoted string, the statement -- and then
// runs something.
$PAYLOADS = array(
    'ends the object' => '0, x: 0}; window.rutPwned = 1; void {y',
    'ends a string' => '"; window.rutPwned = 1; //',
    'ends a string with a quote of the other kind' => "'; window.rutPwned = 1; //",
    'carries a newline' => "0\nwindow.rutPwned = 1;\n",
    'carries a statement terminator' => '0; window.rutPwned = 1',
    'closes the element' => '</script><script>window.rutPwned = 1;</script>',
    'carries a backslash' => 'a\\\\"; window.rutPwned = 1; //',
);

// Every scalar a settings request writes, and what set() must keep for each.
$FIELDS = array('enabled', 'login', 'password', 'auto');

$tests = array();

foreach ($FIELDS as $field) {
    foreach ($PAYLOADS as $shape => $payload) {
        $tests["a {$field} that {$shape} does not become script"] =
            function () use ($field, $payload) {
                $manager = litManager();
                $_REQUEST = array('tracker.example_' . $field => $payload);
                $manager->set();
                $accounts = litAssignedValue($manager->get());
                litAssertTrue(
                    isset($accounts['tracker.example']),
                    'The account is still described'
                );
                $_REQUEST = array();
            };
    }
}

$tests['set() keeps a flag, a number and a text, and nothing else'] = function () {
    $manager = litManager();
    $_REQUEST = array(
        'tracker.example_enabled' => '1, x: 1',
        'tracker.example_auto' => '86400; window.rutPwned = 1',
        'tracker.example_login' => 'someuser',
        'tracker.example_password' => 'correct-horse',
    );
    $manager->set();
    $stored = $manager->accounts['tracker.example'];
    litAssertSame(1, $stored['enabled'], 'enabled is a flag');
    litAssertSame(86400, $stored['auto'], 'auto is a number');
    litAssertSame('someuser', $stored['login'], 'login is the text that was sent');
    litAssertSame('correct-horse', $stored['password'], 'password is the text that was sent');
    litAssertSame(1, $manager->stored, 'The write was stored once');
    litAssertSame(array('tracker.example'), $manager->forgotten, 'The cached session was dropped');
    $_REQUEST = array();
};

$tests['an unchecked box clears the flag'] = function () {
    $manager = litManager();
    $manager->accounts['tracker.example']['enabled'] = 1;
    $_REQUEST = array('tracker.example_enabled' => '0');
    $manager->set();
    litAssertSame(0, $manager->accounts['tracker.example']['enabled'], 'enabled is cleared');
    litAssertSame(0, litAssignedValue($manager->get())['tracker.example']['enabled'],
        'and the page is told so');
    $_REQUEST = array();
};

// Which accounts are enabled is not what is being changed here. getAccount()
// decided that with a php truth test on the same field, so the flag carries
// that same answer -- as a flag rather than as whatever was sent.
$tests['the flag carries the truth value the old code acted on'] = function () {
    foreach (array(array('1', 1), array('0', 0), array('', 0), array('yes', 1)) as $case) {
        list($sent, $expected) = $case;
        $manager = litManager();
        $_REQUEST = array('tracker.example_enabled' => $sent);
        $manager->set();
        litAssertSame($expected, $manager->accounts['tracker.example']['enabled'],
            'A sent ' . var_export($sent, true) . ' is stored as a flag');
        $_REQUEST = array();
    }
};

// An array arrives whenever the parameter is named with brackets, and a
// request chooses its own parameter names.
$tests['a value that is not a scalar is not stored'] = function () {
    $manager = litManager();
    $_REQUEST = array(
        'tracker.example_login' => array('a', 'b'),
        'tracker.example_enabled' => array('1'),
        'tracker.example_auto' => array('86400'),
    );
    $manager->set();
    $stored = $manager->accounts['tracker.example'];
    litAssertSame('', $stored['login'], 'login stays a text');
    litAssertSame(0, $stored['enabled'], 'enabled stays a flag');
    litAssertSame(0, $stored['auto'], 'auto stays a number');
    litAssignedValue($manager->get());
    $_REQUEST = array();
};

// A cache file written before set() normalised anything still holds whatever
// was put there, and get() reads it on the next page load.
$tests['a store poisoned earlier is still emitted as data'] = function () {
    $manager = litManager();
    $manager->accounts['tracker.example']['enabled'] = '0, x: 0}; window.rutPwned = 1; void {y';
    $manager->accounts['tracker.example']['auto'] = '0; window.rutPwned = 1';
    $manager->accounts['tracker.example']['login'] = "</script><script>window.rutPwned = 1;</script>";
    $manager->accounts['tracker.example']['password'] = 'correct-horse';
    $accounts = litAssignedValue($manager->get());
    // A flag, carrying the same truth value getAccount() read off it, and a
    // number: the text around them is gone rather than emitted.
    litAssertSame(1, $accounts['tracker.example']['enabled'], 'The flag reads as a flag');
    litAssertSame(0, $accounts['tracker.example']['auto'], 'The number reads as a number');
    litAssertSame(1, $accounts['tracker.example']['password_set'],
        'password_set stays derived from whether a password is stored');
};

// The same file is read by getInfo(), which action.php serves as json.
$tests['the info answer is typed too'] = function () {
    $manager = litManager();
    $manager->accounts['tracker.example']['enabled'] = '1, x: 1';
    $manager->accounts['tracker.example']['auto'] = '86400abc';
    $info = $manager->getInfo();
    litAssertSame(1, $info[0]['enabled'], 'enabled is a flag');
    litAssertSame(86400, $info[0]['auto'], 'auto is a number');
};

// What the settings page reads back has to be what was typed, escaping and
// all -- the point is that it arrives as data, not that it is thrown away.
$tests['a login full of punctuation survives the round trip'] = function () {
    $login = "o'brien \"x\" <&> \\ /\n\t";
    $manager = litManager();
    $_REQUEST = array('tracker.example_login' => $login);
    $manager->set();
    $accounts = litAssignedValue($manager->get());
    litAssertSame($login, $accounts['tracker.example']['login'],
        'The stored login is handed back exactly');
    $_REQUEST = array();
};

// An account name comes from a filename under plugins/loginmgr/accounts, and
// was concatenated in as an unquoted key.
$tests['an account name is a key, not syntax'] = function () {
    $name = "x'}); window.rutPwned = 1; ({'y";
    $manager = litManager($name);
    $accounts = litAssignedValue($manager->get());
    litAssertTrue(array_key_exists($name, $accounts), 'The name is the key it is');
};

$tests['no account at all is still an object'] = function () {
    $manager = new ProbeLiteralManager();
    $manager->accounts = array();
    litAssertSame('theWebUI.theAccounts = {};' . "\n", $manager->get(),
        'An empty set is an empty object, which is what init.js iterates');
};

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
