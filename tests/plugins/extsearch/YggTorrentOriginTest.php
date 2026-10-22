<?php

/**
 * The YggTorrent search engine and the YggTorrent account of loginmgr have to
 * agree on the tracker's host: the engine builds the download links, and
 * loginmgr logs in for a download only when the link is on the origin the
 * user configured as $yggTorrentOrigin. The engine used to search a domain
 * written into its code and to build each link on whatever host the result
 * page linked to, so a configured origin was not used for the search, and a
 * link off that origin was downloaded as a guest.
 *
 * The real engine, engines.php and loginmgr account are loaded; only fetch()
 * is replaced, by one that records the URL and answers with a result page.
 * loginmgr is not loaded here: the engine's first search loads it, from
 * inside a method, as it does in production.
 */

// The log and the session cache stay out of the shared /tmp and profile.
$extYggTemp = sys_get_temp_dir() . '/extsearch-ygg-origin-' . getmypid();
mkdir($extYggTemp, 0700);
$_ENV['RU_LOG_FILE'] = $extYggTemp . '/application.log';
$_ENV['RU_PROFILE_PATH'] = $extYggTemp . '/share';

require_once(__DIR__ . '/../../../php/util.php');
require_once(__DIR__ . '/../../../plugins/extsearch/engines.php');
require_once(__DIR__ . '/../../../plugins/extsearch/engines/YggTorrent.php');

// Answers every request with $page and records the URL asked for.
class ExtYggProbeEngine extends YggTorrentEngine
{
    public $requests = array();
    public $page = '';

    public function fetch($url, $encode = 1, $method = "GET", $content_type = "", $body = "")
    {
        $this->requests[] = $url;
        $client = new stdClass();
        $client->status = 200;
        $client->results = $this->page;
        return $client;
    }
}

function extYggSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true)
        );
    }
}

function extYggConfigure($origin)
{
    global $yggTorrentOrigin;
    $yggTorrentOrigin = $origin;
}

// A result page in the shape action() parses: $count results announced, and
// one row per id, each linking to its description on $host.
function extYggPage($host, $count, $ids)
{
    $rows = '';
    foreach ($ids as $id) {
        $rows .= '<tr><td><div class="hidden">2145</div>'
            . '<a id="torrent_name" href="' . $host . '/torrent/filmvidéo/film/' . $id . '-name-' . $id . '">Name ' . $id . ' </td>'
            . '<td><a target="' . $id . '" href="#">nfo</a></td>'
            . '<td><div class="hidden">1700000000</div>1 jour</td>'
            . '<td>1.5Go</td><td>10</td><td>5</td><td>2</td></tr>' . "\n";
    }
    return '<html><body><h2>' . $count . ' résultats trouvés</h2><table>' . "\n" . $rows . '</table></body></html>';
}

// Runs a search; a warning or a deprecation is a failure, since it would be
// printed into the answer extsearch is assembling.
function extYggSearch($engine)
{
    $ret = array();
    set_error_handler(function ($number, $message, $file, $line) {
        throw new RuntimeException("PHP diagnostic: {$message} ({$file}:{$line})");
    });
    try {
        $engine->action('some%20film', 'Tout', $ret, 100, false);
    } finally {
        restore_error_handler();
    }
    return $ret;
}

function extYggRemoveTree($path)
{
    foreach (scandir($path) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $child = $path . '/' . $entry;
        if (is_dir($child) && !is_link($child)) {
            extYggRemoveTree($child);
        } else {
            unlink($child);
        }
    }
    rmdir($path);
}

// The first search is made before anything else has loaded loginmgr, so it
// is the engine that includes accounts.php -- from inside action(), where the
// plugin configuration lands in the method's scope unless accounts.php
// declares it global. That include evals plugins/loginmgr/conf.php, whose
// shipped '' resets $yggTorrentOrigin, so the cases configure it afterwards.
extYggSame(false, class_exists('YggTorrentAccount', false), 'nothing has loaded loginmgr before the first search');
$extYggFirstEngine = new ExtYggProbeEngine();
$extYggFirstEngine->page = extYggPage('https://yggtorrent.example', 2, array(101));
$extYggFirstResult = extYggSearch($extYggFirstEngine);
$extYggLoadedByEngine = class_exists('YggTorrentAccount', false);
$extYggFirstGlobal = array_key_exists('yggTorrentOrigin', $GLOBALS) ? $GLOBALS['yggTorrentOrigin'] : null;
// An engine that does not load loginmgr would leave the cases below without
// the account they ask; load it so that they fail on what they assert.
if (!$extYggLoadedByEngine) {
    require_once(__DIR__ . '/../../../plugins/loginmgr/accounts.php');
    require_once(__DIR__ . '/../../../plugins/loginmgr/accounts/YggTorrent.php');
}

$tests = array(
    'the search goes to the configured origin, on every page' => function () {
        extYggConfigure('https://yggtorrent.example');
        $engine = new ExtYggProbeEngine();
        // 120 results are three pages of 50.
        $engine->page = extYggPage('https://yggtorrent.example', 120, array(101, 102));
        extYggSearch($engine);
        extYggSame(3, count($engine->requests), 'one request per page');
        foreach ($engine->requests as $url) {
            extYggSame('https://yggtorrent.example/engine/search/?name=some%20film&',
                substr($url, 0, strlen('https://yggtorrent.example/engine/search/?name=some%20film&')),
                'the search is made on the configured origin');
        }
    },

    'a download link is on the configured origin even when the page links elsewhere' => function () {
        extYggConfigure('https://yggtorrent.example');
        $engine = new ExtYggProbeEngine();
        $engine->page = extYggPage('https://ygg-mirror.example', 2, array(101, 102));
        $ret = extYggSearch($engine);
        extYggSame(array(
            'https://yggtorrent.example/engine/download_torrent?id=101',
            'https://yggtorrent.example/engine/download_torrent?id=102',
        ), array_keys($ret), 'the links are built on the configured origin');
        foreach (array_keys($ret) as $link) {
            extYggSame(true, (new YggTorrentAccount())->test($link),
                'loginmgr claims the link, so the download is made logged in: ' . $link);
        }
        extYggSame('https://ygg-mirror.example/torrent/filmvidéo/film/101-name-101', $ret['https://yggtorrent.example/engine/download_torrent?id=101']['desc'],
            'the description link is the one the page gave');
    },

    'a page that links on the configured origin gives the same entries' => function () {
        extYggConfigure('https://yggtorrent.example');
        $engine = new ExtYggProbeEngine();
        $engine->page = extYggPage('https://yggtorrent.example', 2, array(101, 102));
        $ret = extYggSearch($engine);
        extYggSame(array(
            'https://yggtorrent.example/engine/download_torrent?id=101',
            'https://yggtorrent.example/engine/download_torrent?id=102',
        ), array_keys($ret), 'one entry per row');
        $item = $ret['https://yggtorrent.example/engine/download_torrent?id=101'];
        extYggSame('Name 101', $item['name'], 'the name is read');
        extYggSame('Film/Vidéos > Film', $item['cat'], 'the category is read off the description link');
        extYggSame(5, $item['seeds'], 'the seeders are read');
        extYggSame(2, $item['peers'], 'the leechers are read');
    },

    // The category is read off the path of the description link, whichever
    // host it names or none: binding it to the configured origin, or to the
    // host of the first row, loses it for a mirror's or a relative link.
    'the category is read off a description link on another host or a relative one' => function () {
        extYggConfigure('https://yggtorrent.example');
        foreach (array('https://ygg-mirror.example' => 'another host', '' => 'a relative link') as $host => $what) {
            $engine = new ExtYggProbeEngine();
            $engine->page = extYggPage($host, 2, array(101));
            $ret = extYggSearch($engine);
            $link = 'https://yggtorrent.example/engine/download_torrent?id=101';
            extYggSame(array($link), array_keys($ret), 'one entry for a description on ' . $what);
            extYggSame($host . '/torrent/filmvidéo/film/101-name-101', $ret[$link]['desc'],
                'the description link is the one the page gave, on ' . $what);
            extYggSame('Film/Vidéos > Film', $ret[$link]['cat'], 'the category is read for ' . $what);
        }
    },

    'the origin is used as loginmgr normalises it' => function () {
        extYggConfigure('HTTPS://YggTorrent.Example.:8443/');
        $engine = new ExtYggProbeEngine();
        $engine->page = extYggPage('https://ygg-mirror.example', 2, array(101));
        $ret = extYggSearch($engine);
        extYggSame(0, strpos($engine->requests[0], 'https://yggtorrent.example:8443/engine/search/?'),
            'the search is made on the normalised origin: ' . $engine->requests[0]);
        extYggSame(array('https://yggtorrent.example:8443/engine/download_torrent?id=101'), array_keys($ret),
            'the link is built on the normalised origin');
        extYggSame(true, (new YggTorrentAccount())->test('https://yggtorrent.example:8443/engine/download_torrent?id=101'),
            'and loginmgr claims it');
    },

    'without a usable origin the engine sends nothing and says what to set' => function () {
        global $extYggFirstEngine, $extYggFirstResult, $extYggLoadedByEngine, $extYggFirstGlobal;
        extYggSame(true, $extYggLoadedByEngine, 'the first search loads loginmgr itself');
        extYggSame('', $extYggFirstGlobal, 'the shipped plugin configuration reaches the global scope');
        extYggSame(array(), $extYggFirstEngine->requests, 'the first search, with the shipped configuration, makes no request');
        extYggSame(array(''), array_keys($extYggFirstResult), 'and gives one entry and no link');
        foreach (array('', 'http://yggtorrent.example', 'https://yggtorrent.example/path', 'yggtorrent.example') as $origin) {
            extYggConfigure($origin);
            $engine = new ExtYggProbeEngine();
            $engine->page = extYggPage('https://yggtorrent.example', 2, array(101));
            $ret = extYggSearch($engine);
            extYggSame(array(), $engine->requests, 'no request is made with $yggTorrentOrigin = ' . var_export($origin, true));
            extYggSame(array(''), array_keys($ret), 'one entry and no link with $yggTorrentOrigin = ' . var_export($origin, true));
            foreach (array('$yggTorrentOrigin', 'https origin') as $part) {
                extYggSame(true, strpos($ret['']['name'], $part) !== false,
                    'the entry names the setting and what it takes (' . $part . '): ' . $ret['']['name']);
            }
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
extYggRemoveTree($extYggTemp);
exit($failures === 0 ? 0 : 1);
