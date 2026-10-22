<?php

/**
 * An extsearch engine that declares auth=1 searches logged in: Snoopy asks
 * accountManager::getAccount() which loginmgr account claims the URL, and the
 * account named after the engine is the one meant to answer. It can only
 * answer for a URL on the site it names. commonAccount::test() compares the
 * URL's host against that site's and refuses a URL whose scheme is weaker
 * than the site's, so an engine searching http:// a site the account holds as
 * https:// selects no account at all -- and the engine then searches as a
 * guest, with no diagnostic, which looks exactly like a tracker with nothing
 * to show.
 *
 * The invariant is therefore the origin, not the claim: scheme, host and port
 * as the engine asks for them must be the ones the account names. The path is
 * not part of it, because several accounts scope themselves to the download
 * path on purpose (YggTorrent to /engine/download_torrent, LostFilm to
 * /download.php, Tfile to /forum/) and a search page is not on it.
 *
 * Every engine declaring auth=1 is driven, so a new one joins this on its own.
 * Nothing reaches the network: fetch() is replaced by a recorder and
 * makeClient() throws, which are the two ways commonEngine hands out a client.
 */

$authBase = sys_get_temp_dir() . '/extsearch-auth-origin-' . getmypid();
mkdir($authBase . '/settings', 0700, true);
$_ENV['RU_PROFILE_PATH'] = $authBase;
$_ENV['RU_LOG_FILE'] = $authBase . '/application.log';

require_once(__DIR__ . '/../../../plugins/extsearch/engines.php');
require_once(__DIR__ . '/../../../plugins/loginmgr/accounts.php');

// The four accounts that take their site from configuration name none until
// one is set, and their engines then make no request at all -- which is their
// own behaviour and YggTorrentOriginTest's subject, not this one's. Given an
// origin, they belong here with the rest. These are placeholders in a
// reserved domain; the recorder below is the only thing that ever sees them.
global $yggTorrentOrigin, $abTorrentsOrigin, $revolutionTTOrigin, $redactedOrigin;
$yggTorrentOrigin = 'https://ygg.example';
$abTorrentsOrigin = 'https://abtorrents.example';
$revolutionTTOrigin = 'https://revolutiontt.example';
$redactedOrigin = 'https://redacted.example';

function authAssert($condition, $message)
{
    if (!$condition) throw new RuntimeException($message);
}

function authRemoveTree($path)
{
    if (!is_dir($path) || is_link($path)) {
        if (file_exists($path) || is_link($path)) unlink($path);
        return;
    }
    foreach (array_diff(scandir($path), array('.', '..')) as $name) authRemoveTree($path . '/' . $name);
    rmdir($path);
}

// "scheme://host[:port]" of a URL, folded the way loginmgr folds a site.
function authOrigin($url)
{
    $parts = @parse_url((string) $url);
    if (!is_array($parts) || empty($parts["host"]) || empty($parts["scheme"])) {
        return '<no origin: ' . var_export($url, true) . '>';
    }
    return strtolower($parts["scheme"]) . '://' . rtrim(strtolower($parts["host"]), '.')
        . (isset($parts["port"]) ? ':' . $parts["port"] : '');
}

// The engines that declare auth=1, by name. Read out of the directory rather
// than listed, so that this covers whatever is shipped.
function authEngineNames()
{
    $dir = __DIR__ . '/../../../plugins/extsearch/engines';
    $names = array();
    foreach (scandir($dir) as $entry) {
        if (substr($entry, -4) !== '.php') continue;
        if (preg_match('/[\'"]auth[\'"]\s*=>\s*1\s*[,)]/', file_get_contents($dir . '/' . $entry))) {
            $names[] = basename($entry, '.php');
        }
    }
    sort($names);
    return $names;
}

// Every URL $name's engine asks for during one search.
function authEngineRequests($name)
{
    require_once(__DIR__ . '/../../../plugins/extsearch/engines/' . $name . '.php');
    $engine = $name . 'Engine';
    authAssert(class_exists($engine), $engine . ' is declared');
    $probe = 'AuthProbe' . $name;
    if (!class_exists($probe, false)) {
        eval('class ' . $probe . ' extends ' . $engine . ' {
            public $requests = array();
            public function makeClient($url) {
                throw new RuntimeException("the engine asked for a live client: " . $url);
            }
            public function fetch($url, $encode = 1, $method = "GET", $content_type = "", $body = "") {
                $this->requests[] = $url;
                $client = new stdClass();
                $client->status = 200;
                $client->results = "<html><body></body></html>";
                $client->headers = array();
                return $client;
            }
        }');
    }
    $instance = new $probe();
    $ret = array();
    // A body with no results in it ends most engines after one request; the
    // parse diagnostics that come of handing them an empty page are not this
    // test's subject, and an engine that throws on one still gets asserted on
    // what it asked for before it did.
    try {
        @$instance->action('some%20film', 'all', $ret, 10, true);
    } catch (Throwable $error) {
    }
    return $instance->requests;
}

// The site $name's loginmgr account names.
function authAccountOrigin($name)
{
    $file = __DIR__ . '/../../../plugins/loginmgr/accounts/' . $name . '.php';
    authAssert(is_file($file),
        'the ' . $name . ' engine declares auth=1, so loginmgr has an account of that name to log it in');
    require_once($file);
    $class = $name . 'Account';
    authAssert(class_exists($class), $class . ' is declared');
    $account = new $class();
    return authOrigin($account->url);
}

$tests = array(
    'every engine that searches logged in searches the origin its account names' => function () {
        $names = authEngineNames();
        authAssert(count($names) > 0, 'there are engines declaring auth=1 to check');
        $mismatched = array();
        foreach ($names as $name) {
            $wanted = authAccountOrigin($name);
            $requests = authEngineRequests($name);
            authAssert(count($requests) > 0,
                'the ' . $name . ' engine makes a request once its account names a site');
            foreach ($requests as $url) {
                $origin = authOrigin($url);
                if ($origin !== $wanted) {
                    $mismatched[] = $name . ': account ' . $wanted . ', engine ' . $origin;
                }
            }
        }
        authAssert($mismatched === array(),
            'an engine asking off its account\'s origin is never logged in: ' . implode('; ', array_unique($mismatched)));
    },

    'the two the scheme used to separate are selected for their own search' => function () {
        // Neither of these accounts scopes itself to a path, so for them the
        // origin rule and the account's own answer are the same question, and
        // the answer that matters is the one getAccount() will act on.
        foreach (array('Torrentech', 'mTeam') as $name) {
            $wanted = authAccountOrigin($name);
            authAssert(strpos($wanted, 'https://') === 0, $name . '\'s account names an https site: ' . $wanted);
            $class = $name . 'Account';
            $account = new $class();
            foreach (authEngineRequests($name) as $url) {
                authAssert(authOrigin($url) === $wanted,
                    $name . ' searches ' . $wanted . ', not ' . authOrigin($url));
                authAssert($account->test($url) === true,
                    $name . '\'s account claims the URL the engine searches, so the search is made logged in: ' . $url);
            }
        }
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
            echo 'not ok - ', $name, "\n";
            echo '  ', get_class($error), ': ', $error->getMessage(), "\n";
        }
    }
} finally {
    authRemoveTree($authBase);
}
echo count($tests), ' tests, ', $failures, " failures\n";
exit($failures ? 1 : 0);
