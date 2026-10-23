<?php

/**
 * The ABTorrents, RevolutionTT and Redacted search engines each searched a
 * domain written into their own code, and their loginmgr accounts logged in
 * at the same one. The accounts now take that origin from configuration, so
 * the engines read it from the account of the same name: one host for the
 * search, the download links and the login, and with none configured no
 * search request is made at all -- the one entry returned says what to set.
 *
 * The real engines, engines.php and loginmgr accounts are loaded; only
 * fetch() is replaced, by one that records what was asked for.
 */

$extCoTemp = sys_get_temp_dir() . '/extsearch-origin-' . getmypid();
mkdir($extCoTemp, 0700);
$_ENV['RU_LOG_FILE'] = $extCoTemp . '/application.log';
$_ENV['RU_PROFILE_PATH'] = $extCoTemp . '/share';
mkdir($extCoTemp . '/share', 0700);

require_once(__DIR__ . '/../../../php/util.php');
require_once(__DIR__ . '/../../../plugins/extsearch/engines.php');

// name => array(the loginmgr setting, a query fragment the search URL carries)
$extCoEngines = array(
    'ABTorrents' => array('abTorrentsOrigin', '/browse.php?search='),
    'RevolutionTT' => array('revolutionTTOrigin', '/browse.php?search='),
    'Redacted' => array('redactedOrigin', '/torrents.php?searchstr='),
);

foreach (array_keys($extCoEngines) as $extCoName) {
    require_once(__DIR__ . '/../../../plugins/extsearch/engines/' . $extCoName . '.php');
}

function extCoSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true)
        );
    }
}

function extCoConfigure($setting, $value)
{
    $GLOBALS[$setting] = $value;
}

function extCoRemoveTree($path)
{
    foreach (scandir($path) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $child = $path . '/' . $entry;
        if (is_dir($child) && !is_link($child)) {
            extCoRemoveTree($child);
        } else {
            unlink($child);
        }
    }
    rmdir($path);
}

/**
 * The real engine with its transport recorded. Every request the engine would
 * make is in $requests, whether or not a reply could be parsed, so "no
 * request is made" is read off the list rather than off what came back.
 */
function extCoProbe($name)
{
    $probe = 'ExtCoProbe' . $name . 'Engine';
    if (!class_exists($probe, false)) {
        eval('class ' . $probe . ' extends ' . $name . 'Engine {
            public $requests = array();
            public $page = "";
            public function fetch($url, $encode = 1, $method = "GET", $content_type = "", $body = "")
            {
                $this->requests[] = $url;
                $client = new stdClass();
                $client->status = 200;
                $client->results = $this->page;
                return $client;
            }
        }');
    }
    return new $probe();
}

// A warning or a deprecation is a failure: it would be printed into the
// answer extsearch is assembling.
function extCoSearch($engine)
{
    $ret = array();
    set_error_handler(function ($number, $message, $file, $line) {
        throw new RuntimeException("PHP diagnostic: {$message} ({$file}:{$line})");
    });
    try {
        $engine->action('some%20name', 'all', $ret, 100, true);
    } finally {
        restore_error_handler();
    }
    return $ret;
}

// The first search is made before anything else has loaded loginmgr, so it is
// the engine that includes accounts.php -- from inside action(), where the
// plugin configuration lands in the method's scope unless accounts.php
// declares it global. That include evals plugins/loginmgr/conf.php, whose
// shipped '' resets the settings, so the cases configure them afterwards.
extCoSame(false, class_exists('ABTorrentsAccount', false),
    'nothing has loaded loginmgr before the first search');
$extCoFirst = extCoProbe('ABTorrents');
$extCoFirstResult = extCoSearch($extCoFirst);
$extCoLoadedByEngine = class_exists('ABTorrentsAccount', false);

$tests = array(
    'the engine reads loginmgr\'s setting, and the first search is what loads it' => function () {
        global $extCoLoadedByEngine, $extCoFirst, $extCoFirstResult, $extCoEngines;
        extCoSame(true, $extCoLoadedByEngine, 'the engine loads the loginmgr account it reads');
        extCoSame(true, array_key_exists('abTorrentsOrigin', $GLOBALS),
            'and the setting reaches the global scope from inside action()');
        extCoSame(array(), $extCoFirst->requests, 'the shipped configuration searches nothing');
        extCoSame(array(''), array_keys($extCoFirstResult), 'and returns one entry with no link');
    },

    'with no origin the search sends nothing and says what to set' => function () {
        global $extCoEngines;
        foreach ($extCoEngines as $name => $nfo) {
            foreach (array('', 'http://tracker.example', 'tracker.example',
                'https://tracker.example/browse.php', null, array('https://tracker.example')) as $configured) {
                extCoConfigure($nfo[0], $configured);
                $engine = extCoProbe($name);
                $ret = extCoSearch($engine);
                extCoSame(array(), $engine->requests,
                    $name . ' searches nothing with ' . var_export($configured, true));
                extCoSame(array(''), array_keys($ret),
                    $name . ': one entry and no link with ' . var_export($configured, true));
                foreach (array($name . ':', '$' . $nfo[0], 'https origin', 'loginmgr') as $part) {
                    extCoSame(true, strpos($ret['']['name'], $part) !== false,
                        $name . ': the entry names ' . $part . ': ' . $ret['']['name']);
                }
            }
        }
    },

    'a configured origin is the only host the search asks' => function () {
        global $extCoEngines;
        foreach ($extCoEngines as $name => $nfo) {
            foreach (array(
                'https://tracker.example' => 'https://tracker.example',
                'https://TRACKER.Example./' => 'https://tracker.example',
                'https://ww4.tracker.example:8443' => 'https://ww4.tracker.example:8443',
            ) as $configured => $origin) {
                extCoConfigure($nfo[0], $configured);
                $engine = extCoProbe($name);
                extCoSearch($engine);
                extCoSame(true, count($engine->requests) > 0,
                    $name . ' searches once the origin is set');
                foreach ($engine->requests as $url) {
                    extCoSame(0, strpos($url, $origin . $nfo[1]),
                        $name . ': every request is built on the configured origin: ' . $url);
                }
            }
        }
    },

    'the engine and its loginmgr account agree on the host' => function () {
        global $extCoEngines;
        foreach ($extCoEngines as $name => $nfo) {
            extCoConfigure($nfo[0], 'https://tracker.example');
            $engine = extCoProbe($name);
            extCoSearch($engine);
            $class = $name . 'Account';
            $account = new $class();
            foreach ($engine->requests as $url) {
                extCoSame(true, $account->test($url),
                    $name . ': loginmgr claims the URL its engine searches: ' . $url);
            }
            extCoSame('https://tracker.example', $account->url,
                $name . ': the account and the engine read one setting');
        }
    },

    'no engine names a tracker domain in its own source any more' => function () {
        global $extCoEngines;
        foreach ($extCoEngines as $name => $nfo) {
            $source = file_get_contents(__DIR__ . '/../../../plugins/extsearch/engines/' . $name . '.php');
            // The string literals only: a comment may name a host as an
            // example, and an example is not what gets fetched.
            $named = array();
            foreach (token_get_all($source) as $token) {
                if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING
                    && preg_match('@https?://@i', $token[1])) {
                    $named[] = $token[1];
                }
            }
            extCoSame(array(), $named,
                $name . ' builds every URL on the configured origin, and names no host');
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
extCoRemoveTree($extCoTemp);
exit($failures === 0 ? 0 : 1);
