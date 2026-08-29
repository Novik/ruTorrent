<?php

/**
 * Unit test for tolokaAccount::login() -- the form loginmgr posts to
 * toloka.to to open a session.
 *
 * The account is loaded the way KinozalAccountTest loads its own: accounts.php
 * is deliberately NOT required (it evals the plugin config and pulls in
 * cache.php / Snoopy.class.inc, none of which login() touches), so a minimal
 * commonAccount base is all the real account file needs.
 *
 * What is asserted is the request that goes on the wire: which fields the form
 * carries, that the credentials survive encoding, and that posting it raises no
 * PHP diagnostic -- the account used to interpolate a variable that was never
 * assigned, so every login emitted a warning and sent a field it could not
 * fill.
 */

require_once(__DIR__ . '/../rutracker_check/TestLib.php');

abstract class commonAccount
{
    public $url = 'http://abstract.com';

    abstract protected function isOK($client);
    abstract protected function login($client, $login, $password, &$url, &$method, &$content_type, &$body, &$is_result_fetched);
}

require_once(testFindRepoRoot() . '/plugins/loginmgr/accounts/Toloka.php');

// login() calls exactly two things on the Snoopy client.
class TolokaFakeClient
{
    public $results = '';
    public $status = 200;
    public $referer = null;
    public $cookies = array();
    public $requests = array();
    public $cookiesStored = 0;

    public function fetch($url, $method = 'GET', $content_type = '', $body = '')
    {
        $this->requests[] = array(
            'url' => $url,
            'method' => $method,
            'content_type' => $content_type,
            'body' => $body,
        );
        return true;
    }

    public function setcookies()
    {
        $this->cookiesStored++;
    }
}

// Every PHP diagnostic becomes a failure: an undefined variable in a login
// flow is a bug, not noise, and the warning it prints lands in the middle of
// the answer loginmgr's callers are parsing.
function tolokaStrict($callback)
{
    set_error_handler(function ($number, $message, $file, $line) {
        throw new RuntimeException("PHP diagnostic: {$message} ({$file}:{$line})");
    });
    try {
        return call_user_func($callback);
    } finally {
        restore_error_handler();
    }
}

// Runs the real login() against a fake client and hands back both the client
// and the by-reference arguments the account may have rewritten.
function tolokaLogin($login, $password, $requested = 'https://toloka.to/download.php?id=7')
{
    $account = new tolokaAccount();
    $client = new TolokaFakeClient();
    $url = $requested;
    $method = 'GET';
    $content_type = '';
    $body = '';
    $is_result_fetched = true;
    $arguments = array($client, $login, $password, &$url, &$method, &$content_type, &$body, &$is_result_fetched);
    $reflection = new ReflectionMethod('tolokaAccount', 'login');
    if (PHP_VERSION_ID < 80100) {
        $reflection->setAccessible(true);
    }
    $returned = tolokaStrict(function () use ($reflection, $account, $arguments) {
        return $reflection->invokeArgs($account, $arguments);
    });
    return array(
        'returned' => $returned,
        'client' => $client,
        'url' => $url,
        'method' => $method,
        'content_type' => $content_type,
        'body' => $body,
        'is_result_fetched' => $is_result_fetched,
    );
}

function tolokaPostedFields($body)
{
    $fields = array();
    parse_str($body, $fields);
    return $fields;
}

$suite = new StrictTestSuite();

$suite->test('logging in raises no PHP diagnostic', function () {
    // The form used to be built from an undefined $redirect. On PHP 8 that is
    // a warning per login attempt plus, since 8.1, a deprecation from passing
    // null to rawurlencode().
    $run = tolokaLogin('user', 'secret');
    strictAssertSame(true, $run['returned'], 'a successful post must report success');
});

$suite->test('the login post carries only fields the form has', function () {
    $run = tolokaLogin('user', 'secret');
    strictAssertSame(1, count($run['client']->requests), 'login posts exactly one request');
    $request = $run['client']->requests[0];
    strictAssertSame('https://toloka.to/login.php', $request['url'], 'the post goes to login.php');
    strictAssertSame('POST', $request['method'], 'the login is a POST');
    strictAssertSame('application/x-www-form-urlencoded', $request['content_type'], 'the form is url-encoded');

    $fields = tolokaPostedFields($request['body']);
    // redirect= was carried over from the rutracker account this file was
    // copied from, where login() assigns it. Here nothing ever did, so the
    // field went out empty; it is dropped rather than filled in, because this
    // account never lets the login answer stand in for the requested page.
    strictAssertSame(false, array_key_exists('redirect', $fields),
        'the form must not carry a field the account cannot fill: ' . $request['body']);
    strictAssertSame(
        array('username', 'password', 'login', 'autologin', 'ssl'),
        array_keys($fields),
        'unexpected form fields: ' . $request['body']
    );
});

$suite->test('the credentials survive url-encoding', function () {
    // & = + % and a space all have to come back byte for byte, or the account
    // logs in as somebody else, or not at all.
    $login = 'user&name=x';
    $password = 'p@ss word&=+#%2F';
    $run = tolokaLogin($login, $password);
    $fields = tolokaPostedFields($run['client']->requests[0]['body']);
    strictAssertSame($login, $fields['username'], 'the login name must round-trip');
    strictAssertSame($password, $fields['password'], 'the password must round-trip');
});

$suite->test('the account does not claim the requested page was fetched', function () {
    // commonAccount::fetch() only skips its own fetch of the requested url when
    // login() sets this; the login answer here is the forum index, never the
    // torrent that was asked for.
    $run = tolokaLogin('user', 'secret');
    strictAssertSame(false, $run['is_result_fetched'],
        'the caller still has to fetch the requested page itself');
    strictAssertSame('https://toloka.to/download.php?id=7', $run['url'],
        'login() must leave the requested url alone');
    strictAssertSame(1, $run['client']->cookiesStored, 'the session cookies are stored once');
});

exit($suite->run());
