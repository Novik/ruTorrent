<?php

/**
 * Unit test for KinozalTVAccount::isOK() -- loginmgr's "are we still logged
 * in?" detector for Kinozal.
 *
 * accounts.php is deliberately NOT loaded: it evals the plugin config, pulls
 * in cache.php/Snoopy.class.inc and, through them, the real rXMLRPC* classes,
 * none of which isOK() touches. The account file itself has no requires, so a
 * minimal commonAccount base is all it needs to load -- the code under test is
 * the real one, straight from plugins/loginmgr/accounts/KinozalTV.php.
 */

require_once(__DIR__ . '/../rutracker_check/TestLib.php');

abstract class commonAccount
{
    public $url = 'http://abstract.com';

    abstract protected function isOK($client);
    abstract protected function login($client, $login, $password, &$url, &$method, &$content_type, &$body, &$is_result_fetched);
}

require_once(testFindRepoRoot() . '/plugins/loginmgr/accounts/KinozalTV.php');

// isOK() and login() test double.
class KinozalFakeClient
{
    public $status = 200;
    public $results = '';
    public $referer = '';
    public $cookies = array();
    public $responses = array();
    public $unreachable = null;

    public function __construct($results = '', $status = 200)
    {
        $this->results = $results;
        $this->status = $status;
    }

    public function fetch($url, $method = 'GET', $contentType = '', $body = '')
    {
        if ($this->unreachable !== null && $url === $this->unreachable) {
            return false;
        }
        if (isset($this->responses[$url])) {
            $resp = $this->responses[$url];
            $this->status = $resp['status'] ?? 200;
            $this->results = $resp['results'] ?? '';
        }
        // Snoopy::fetch() returns the transport result, not a verdict on the
        // status: it answers true for a 500 as readily as for a 200. A double
        // that refused 4xx/5xx here would do the rejecting that the code under
        // test is supposed to do, and a reverted fix would still pass.
        return true;
    }

    public function setcookies()
    {
        $this->cookies['uid'] = '12345';
        $this->cookies['pass'] = 'abcde';
    }
}

function kinozalIsOK($body, $status = 200)
{
    $account = new KinozalTVAccount();
    $method = new ReflectionMethod('KinozalTVAccount', 'isOK');
    if (PHP_VERSION_ID < 80100) {
        $method->setAccessible(true);
    }
    return $method->invoke($account, new KinozalFakeClient($body, $status));
}

function kinozalLogin($client, $user = 'user', $pass = 'pass')
{
    $account = new KinozalTVAccount();
    $method = new ReflectionMethod('KinozalTVAccount', 'login');
    if (PHP_VERSION_ID < 80100) {
        $method->setAccessible(true);
    }
    $url = '';
    $httpMethod = 'GET';
    $contentType = '';
    $body = '';
    $isFetched = false;
    return $method->invokeArgs($account, array($client, $user, $pass, &$url, &$httpMethod, &$contentType, &$body, &$isFetched));
}

// Captured from https://kinozal.guru/login.php on 2026-08-07: type= carries no
// quotes and size=/id= sit between it and name=, so the old two-attribute
// probe ('type="password" name="password"') finds nothing here.
function kinozalLoginPage()
{
    return '<div class=pad0x0x5x0><ul class=lis><li class=mn><a href="/login.php">Вход</a></li>'
        . '<li><a href="/signup.php">Регистрация в Кинозал.GURU</a></li></ul></div>'
        . '<form method=post action="/takelogin.php" name=upt">'
        . '<input type=text size=35 id="username" name="username" value="">'
        . '<input type=password size=35 id="password" name="password" value="">'
        . '<input class=buttonS type=submit value=" Войти "></form>';
}

// The markup the original detector was written against.
function kinozalLegacyLoginPage()
{
    return '<form method=post action="/takelogin.php">'
        . '<input type="text" name="username" value="">'
        . '<input type="password" name="password" value=""></form>';
}

// Captured from get_srv_details.php without a session (HTTP 200, UTF-8).
function kinozalUnauthorizedAnswer()
{
    return 'Вы не зарегистрированный пользователь или не авторизированы, чтобы '
        . 'зарегистрироваться пройдите <a href=\'/signup.php\' class=\'sba\'>сюда</a>.';
}

$suite = new StrictTestSuite();

$suite->test('today\'s login form is recognised as a dead session', function () {
    strictAssertSame(false, kinozalIsOK(kinozalLoginPage()),
        'the unquoted type= / reordered attribute form must not read as logged in');
});

$suite->test('the older quoted login form is still recognised', function () {
    strictAssertSame(false, kinozalIsOK(kinozalLegacyLoginPage()),
        'the markup the original detector targeted must keep failing the check');
});

$suite->test('the not-authorized answer of get_srv_details is a dead session', function () {
    // This one never renders a login form, so the password-field marker alone
    // cannot see it -- yet it is the answer the checker actually receives, and
    // loginmgr must re-login on it rather than pass the text upstream.
    strictAssertSame(false, kinozalIsOK(kinozalUnauthorizedAnswer()),
        'the guest answer of get_srv_details.php must not read as logged in');
});

$suite->test('a logged-in page reads as a live session', function () {
    $page = '<div class="mn"><a href="/userdetails.php?id=20244841">fessfess</a>'
        . '<a href="/logout.php">Выход</a></div>'
        . '<form action="/browse.php" method="get" id="srchform">'
        . '<input type="text" class="inp" id="s" name="s" size="15" value=""></form>';
    strictAssertSame(true, kinozalIsOK($page), 'a page behind the login wall is fine');
});

$suite->test('authorized tracker answers read as a live session', function () {
    strictAssertSame(
        true,
        kinozalIsOK('<ul><li>Инфо хеш: ' . str_repeat('A', 40) . '</li><li>Размер части торрента: 2 МБ</li></ul>'),
        'the details answer must not be mistaken for a login wall'
    );
    strictAssertSame(true, kinozalIsOK('Торрент файл не найден.'),
        'a removed topic is an answer from the tracker, not a dead session');
});

$suite->test('torrent bytes read as a live session', function () {
    $raw = 'd8:announce31:http://tr2.torrent4me.com/ann?uk=X4:infod6:lengthi1e'
        . '4:name9:movie.mkv12:piece lengthi16384e6:pieces20:' . str_repeat("\0", 20) . 'ee';
    strictAssertSame(true, kinozalIsOK($raw),
        'a downloaded torrent must never be mistaken for a login wall');
});

$suite->test('a torrent whose comment names the signup page is still a torrent', function () {
    // The registration link is a marker of a guest PAGE. A torrent is payload
    // and may carry any bytes at all, so reading the marker out of one would
    // cost a re-login, and the cached session, on every download of it.
    $torrent = 'd8:announce31:http://tr.kinozal.guru/ann?uk=X7:comment28:see /signup.php to register'
        . '4:infod6:lengthi1e4:name9:movie.mkv12:piece lengthi16384eee';
    strictAssertSame(true, kinozalIsOK($torrent),
        'a torrent must not be read as a login wall because of its comment');
});

$suite->test('login reports whether the exchange happened, not whether it was accepted', function () {
    // The verdict belongs to commonAccount::fetch()/check(), which apply it to
    // this same answer the moment login() returns; the other twenty-four
    // accounts report the exchange and nothing more, and a login() that judged
    // for itself would make "false" mean both "the tracker did not answer" and
    // "the credentials were refused", which the caller cannot tell apart.
    $client = new KinozalFakeClient();
    $client->responses = array(
        'https://kinozal.guru' => array('status' => 200, 'results' => '<html>main page</html>'),
        'https://kinozal.guru/takelogin.php' => array('status' => 200, 'results' => kinozalLoginPage()),
    );
    strictAssertSame(true, kinozalLogin($client, 'baduser', 'badpass'),
        'both requests went through, which is all login() is asked');
    strictAssertSame(false, kinozalIsOK($client->results),
        'and the guest form it came back with is what the caller then refuses');
    strictAssertSame(true, isset($client->cookies['uid']), 'cookies are captured either way');
});

$suite->test('login fails when the tracker cannot be reached at all', function () {
    $client = new KinozalFakeClient();
    $client->responses = array('https://kinozal.guru' => array('status' => 200, 'results' => ''));
    $client->unreachable = 'https://kinozal.guru/takelogin.php';
    strictAssertSame(false, kinozalLogin($client, 'user', 'pass'),
        'a request that did not go through is a failed login');
});

exit($suite->run());
