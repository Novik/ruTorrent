<?php

/**
 * commonAccount -- how loginmgr decides whether a stored session is still good,
 * and what it does when it is not.
 *
 * The subject is the orchestration, not one predicate: the interesting claims
 * are about which of fetch()'s two branches runs, whether a credential POST is
 * spent, and whether the cookie cache survives. So most of this drives the real
 * public fetch() and check() with a scripted client and a recording cache,
 * rather than reaching into isOK() through reflection.
 *
 * The real plugins/loginmgr/accounts.php is loaded, so the base class under
 * test is the shipped one. That rules out tests/plugins/rutracker_check/
 * TestLib.php, whose rTorrentSettings/rXMLRPCRequest doubles collide with the
 * classes accounts.php reaches through php/cache.php -- the same reason
 * tests/plugins/history/HistoryDataTest.php carries its own small runner.
 */

require_once(__DIR__ . '/../../../plugins/loginmgr/accounts.php');
foreach (glob(__DIR__ . '/../../../plugins/loginmgr/accounts/*.php') as $accountFile) {
    require_once($accountFile);
}

function caAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true)
        );
    }
}

// Every concrete account, in a stable order so a failure names the same class
// twice running.
function caAccountClasses()
{
    $classes = array();
    foreach (get_declared_classes() as $class) {
        if (is_subclass_of($class, 'commonAccount') && $class !== 'ProbeAccount') {
            $classes[] = $class;
        }
    }
    sort($classes);
    return $classes;
}

/**
 * A Snoopy stand-in. fetch() returns the scripted transport result whatever the
 * status, because the real one does: php/Snoopy.class.inc returns the transport
 * result and answers true for a 500 as readily as for a 200. That is the premise
 * of the whole guard, so a double that returned false for 4xx/5xx would let a
 * reverted fix pass.
 */
class CAClient
{
    public $status = 200;
    public $results = '';
    public $lastredirectaddr = '';
    public $referer = '';
    public $cookies = array();
    public $fetches = 0;
    public $filename = 'movie.torrent';
    private $queue;

    public function __construct($queue = array())
    {
        $this->queue = $queue;
    }

    public function fetch($url, $method = 'GET', $contentType = '', $body = '')
    {
        $this->fetches++;
        if ($this->queue) {
            $answer = array_shift($this->queue);
            $this->apply($answer);
            return isset($answer[2]) ? $answer[2] : true;
        }
        return true;
    }

    public function queueAnswers($queue)
    {
        $this->queue = $queue;
    }

    public function apply($answer)
    {
        $this->status = $answer[0];
        $this->results = $answer[1];
    }

    public function setcookies()
    {
        $this->cookies['sid'] = 'abc';
    }

    public function get_filename()
    {
        return $this->filename;
    }
}

// A privateData that counts what was asked of it instead of touching rCache.
class CARecordingData extends privateData
{
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

// A minimal account: one guest marker, and a login() whose answer the test
// scripts. Everything else is the real base class.
class ProbeAccount extends commonAccount
{
    public $url = 'https://tracker.example';
    public $data;
    public $logins = 0;
    public $loginAnswer = null;     // array(status, body), or null for "login failed"
    public $urlSeenByLogin = 'unset';

    public function __construct()
    {
        $this->data = new CARecordingData('Probe');
    }

    protected function loadData($client = null)
    {
        return $this->data;
    }

    protected function isOK($client)
    {
        return strpos($client->results, 'name="password"') === false;
    }

    protected function login($client, $login, $password, &$url, &$method, &$content_type, &$body, &$is_result_fetched)
    {
        $this->logins++;
        $this->urlSeenByLogin = $url;
        $is_result_fetched = false;
        if ($this->loginAnswer === null) {
            return false;
        }
        $client->apply($this->loginAnswer);
        $client->setcookies();
        return true;
    }
}

// A page carrying none of the guest markers any account looks for, so every
// isOK() under accounts/ reads it as authenticated. It is the control: the
// refusals below differ from it in the answer's status or body alone.
function caLivePage()
{
    return '<html><body><a href="/logout.php">Log out</a></body></html>';
}

function caGuestPage()
{
    return '<html><form action="/login.php"><input type="password" name="password"></form></html>';
}

// A cached session that has already been loaded from disk.
function caWarmProbe($queue)
{
    $account = new ProbeAccount();
    $account->data->loaded = true;
    return array($account, new CAClient($queue));
}

function caFetch($account, $client)
{
    return $account->fetch($client, 'https://tracker.example/dl.php?id=1', 'user', 'pass', 'GET', '', '');
}

// A client already holding an answer, for the paths that do not fetch first.
function caHolding($status, $body, $lastRedirect = '')
{
    $client = new CAClient();
    $client->status = $status;
    $client->results = $body;
    $client->lastredirectaddr = $lastRedirect;
    return $client;
}

function caPostFetch($class, $client, $url = 'https://tracker.example/page.php')
{
    $method = new ReflectionMethod($class, 'isOKPostFetch');
    if (PHP_VERSION_ID < 80100) {
        $method->setAccessible(true);
    }
    return $method->invoke(new $class(), $client, $url, 'GET', '', '');
}

$tests = array(

    // ---- what fetch() does with each kind of answer -------------------------

    'a live page on the cached path is used, and costs no login' => function () {
        list($account, $client) = caWarmProbe(array(array(200, caLivePage())));
        caAssertSame(true, caFetch($account, $client), 'the cached session serves the page');
        caAssertSame(0, $account->logins, 'no credential POST is spent on a working session');
        caAssertSame(0, $account->data->removed, 'the cookies are kept');
        caAssertSame(1, $client->fetches, 'exactly one request leaves the box');
    },

    'a guest page is the one answer that re-logs in' => function () {
        // The only evidence that the session died: the tracker answered, and
        // answered as a guest.
        list($account, $client) = caWarmProbe(array(array(200, caGuestPage()), array(200, caLivePage())));
        $account->loginAnswer = array(200, caLivePage());
        caAssertSame(true, caFetch($account, $client), 'the re-login recovers the fetch');
        caAssertSame(1, $account->logins, 'exactly one login');
        caAssertSame(1, $account->data->stored, 'the new session is cached');
        caAssertSame(0, $account->data->removed, 'and not thrown away again');
    },

    'a server error costs no login and keeps the session' => function () {
        // A 5xx says nothing about whether the session is alive, so logging in
        // again is not a repair -- it spends a credential POST on a tracker
        // that is already failing. Callers that loop over a torrent list would
        // otherwise turn one outage into a login per torrent.
        list($account, $client) = caWarmProbe(array(array(503, '<html>bad gateway</html>')));
        caAssertSame(false, caFetch($account, $client), 'the failure is reported');
        caAssertSame(0, $account->logins, 'no credential POST is spent on an outage');
        caAssertSame(0, $account->data->removed, 'cookies never shown to be stale are kept');
        caAssertSame(1, $client->fetches, 'and no second request is made');
    },

    'a cached request that cannot reach the tracker keeps its session' => function () {
        // A false Snoopy::fetch() means no response arrived at all. It cannot
        // establish that the cached cookies are stale, so this attempt must
        // stop before the Kinozal credential POST and leave that session alone.
        list($account, $client) = caWarmProbe(array(array(0, '', false)));
        $client->cookies = array('sid' => 'cached');
        caAssertSame(false, caFetch($account, $client), 'a transport failure is reported');
        caAssertSame(0, $account->logins, 'no credential POST is spent without an answer');
        caAssertSame(0, $account->data->removed, 'the cached session is not deleted');
        caAssertSame(array('sid' => 'cached'), $client->cookies, 'the cached cookies stay in place');
        caAssertSame(1, $client->fetches, 'the failed request is not retried through login');
    },

    'an unchanged conditional answer is a live session' => function () {
        // plugins/rss sends If-None-Match and reads $client->status itself. A
        // 304 is empty on purpose; judging it by its absent body would turn
        // every unchanged poll of an authenticated feed into a fresh login.
        list($account, $client) = caWarmProbe(array(array(304, '')));
        caAssertSame(true, caFetch($account, $client), '304 is an answer, and an authenticated one');
        caAssertSame(0, $account->logins, 'an unchanged feed costs no login');
        caAssertSame(0, $account->data->removed, 'and does not drop the session');
    },

    'a redirect that was not followed is neither live nor dead' => function () {
        // A followed chain ends at the status of its last hop, so a 3xx here
        // means Snoopy stopped following. The boilerplate body a server puts on
        // one carries no guest marker, so it used to read as a live session.
        foreach (array('', '<html><head><title>302 Found</title></head><body>moved</body></html>') as $stub) {
            list($account, $client) = caWarmProbe(array(array(302, $stub)));
            caAssertSame(false, caFetch($account, $client), 'a 302 is not a page to hand to the caller');
            caAssertSame(0, $account->logins, 'and not evidence that the session died');
            caAssertSame(0, $account->data->removed, 'so the cookies stay');
        }
    },

    'an empty body is not a live session' => function () {
        list($account, $client) = caWarmProbe(array(array(200, '')));
        caAssertSame(false, caFetch($account, $client), 'nothing arrived, so nothing is served');
        caAssertSame(0, $account->logins, 'and no login is spent guessing why');
    },

    'a body the transport could not decompress is refused, not fatal' => function () {
        // php/Snoopy.class.inc shells out to gzip when it has no gzinflate();
        // a failed decompression used to leave exec()'s output array in
        // ->results, and every marker test under accounts/ is a strpos(),
        // which is fatal on an array in PHP 8.
        list($account, $client) = caWarmProbe(array(array(200, array('gzip: stdin: not in gzip format'))));
        caAssertSame(false, caFetch($account, $client), 'a non-string body is refused');
        caAssertSame(0, $account->logins, 'and is not read as a dead session either');
    },

    // ---- what fetch() does on the login path --------------------------------

    'a login answered with cookies and no body still authenticates' => function () {
        // A login endpoint may answer 204, or a 30x to the landing page, and do
        // all its work in Set-Cookie. It is the fetch that follows which proves
        // whether the session took, so the marker test cannot be applied to a
        // body that is not there.
        $account = new ProbeAccount();
        $account->loginAnswer = array(302, '');
        $client = new CAClient(array(array(200, caLivePage())));
        caAssertSame(true, caFetch($account, $client), 'a bodyless login answer is not a refusal');
        caAssertSame(1, $account->data->stored, 'the session it produced is cached');
    },

    'a login answered with the guest form is a refusal' => function () {
        $account = new ProbeAccount();
        $account->loginAnswer = array(200, caGuestPage());
        $client = new CAClient();
        caAssertSame(false, caFetch($account, $client), 'wrong credentials do not authenticate');
        caAssertSame(1, $account->data->removed, 'and the stale cache is dropped');
    },

    // ---- check(), the auto-relogin cron -------------------------------------

    'check() hands login() a usable url' => function () {
        // These are by-reference parameters, and several accounts read them
        // before writing. Undeclared, they arrived as null, and an account
        // whose login() begins by fetching $url could never authenticate here.
        $account = new ProbeAccount();
        $account->loginAnswer = array(200, caLivePage());
        $account->check(new CAClient(), 'user', 'pass', 0);
        caAssertSame('https://tracker.example', $account->urlSeenByLogin, 'login() is given the account url');
        caAssertSame(1, $account->data->stored, 'a renewed session is cached');
    },

    'check() never deletes a session it merely failed to renew' => function () {
        // The job exists to REFRESH a session. Deleting one because this run
        // could not reach the tracker leaves the user worse off than not
        // running it at all; a session that really died is found by fetch().
        $account = new ProbeAccount();
        $account->loginAnswer = null;
        $account->check(new CAClient(), 'user', 'pass', 0);
        caAssertSame(0, $account->data->removed, 'the stored session survives a failed renewal');
        caAssertSame(0, $account->data->stored, 'and nothing bogus is stored either');
    },

    // ---- the family, and its one override -----------------------------------

    'every account still accepts a page from behind the login wall' => function () {
        $classes = caAccountClasses();
        caAssertSame(true, count($classes) > 0, 'the account files were found at all');
        foreach ($classes as $class) {
            caAssertSame(true, caPostFetch($class, caHolding(200, caLivePage())),
                $class . ' must accept an answer that did arrive');
        }
    },

    'no account reads an answer that never arrived as a live session' => function () {
        foreach (caAccountClasses() as $class) {
            foreach (array(array(200, ''), array(503, caLivePage()), array(302, caLivePage()),
                array(200, array('gzip: not in gzip format'))) as $answer) {
                caAssertSame(false, caPostFetch($class, caHolding($answer[0], $answer[1])),
                    $class . ' on status ' . $answer[0]);
            }
        }
    },

    'the one override of isOKPostFetch hands the fallthrough back' => function () {
        // LostFilm is the only override in accounts/, and its fallthrough used
        // to answer true on its own -- the one way to skip the base verdict,
        // which on the cached path skipped isOK() with it.
        caAssertSame(false, caPostFetch('LostFilmAccount', caHolding(200, '')),
            'LostFilm is not exempt from the guard its siblings get');
        caAssertSame(false, caPostFetch('LostFilmAccount', caHolding(503, caLivePage())),
            'nor from the status half of it');
    },

    'the override judges the answer the caller asked for' => function () {
        // Its recovery branch fetches details.php onto the same client. Once it
        // has, ->results is that page and no longer the answer to $url, so the
        // fallthrough verdict has to be taken before the branch runs.
        // The dlt marker is absent from details.php, so the branch falls
        // through -- but only after replacing ->results with a page that would
        // pass on its own.
        $client = caHolding(200, caGuestPage(), '/browse.php?cat=1');
        $client->queueAnswers(array(array(200, caLivePage())));
        caAssertSame(false,
            caPostFetch('LostFilmAccount', $client, 'https://lostfilm.tv/download.php?id=7&x'),
            'the guest answer to the download url decides, not details.php');
    },

    'the override does not accept a download by its headers alone' => function () {
        // get_filename() reads Content-Disposition and never the status or the
        // body, so on its own it accepts a 500 page and a body Snoopy could not
        // decompress -- the shapes the base class exists to refuse.
        $client = caHolding(200, '<html>ok</html>', '/browse.php?cat=1');
        $client->queueAnswers(array(
            // details.php, carrying the dlt token in the shape the regex wants
            array(200, '<a href="/download.php?id=7&yyy" onMouseOver="setCookie(\'dlt\',\'deadbeef\'"></a>'),
            // the retried download: a filename header, but an error page
            array(500, '<html>error</html>'),
        ));
        caAssertSame(false,
            caPostFetch('LostFilmAccount', $client, 'https://lostfilm.tv/download.php?id=7&x'),
            'a 500 carrying a filename header is not a torrent');
    },

    // ---- one tracker-specific claim worth pinning ---------------------------

    'a downloaded torrent is not mistaken for a login wall' => function () {
        // Kinozal reads a guest answer off the registration link, which a
        // torrent may legitimately carry in its comment field.
        $torrent = 'd8:announce31:http://tr.kinozal.guru/ann?uk=X7:comment28:see /signup.php to register'
            . '4:infod6:lengthi1e4:name9:movie.mkv12:piece lengthi16384eee';
        caAssertSame(true, caPostFetch('KinozalTVAccount', caHolding(200, $torrent)),
            'a torrent whose comment names /signup.php is still a torrent');
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
