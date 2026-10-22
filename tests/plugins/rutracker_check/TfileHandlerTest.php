<?php

/**
 * Tfile handler: which host it reads is configuration, not a constant.
 *
 * The handler fetches a forum topic and hands download.php's answer to
 * createTorrent(), which replaces the torrent rtorrent is running -- so the
 * host it asks is choosing who gets to write that. It used to be a domain in
 * the source whose registration has since changed hands; loginmgr already
 * withdrew that domain from the Tfile account and took $tfileOrigin in its
 * place, and this handler now reads the same setting through the same
 * account, so the two halves cannot name different sites.
 *
 * Nothing here reaches the network: the Snoopy in TestLib answers only
 * queued URLs and throws on any other, which is also what asserts that the
 * old domain is never asked for.
 */

define('TESTLIB_HANDLER_STUBS', 1);
require_once(__DIR__ . '/TestLib.php');
require_once(testFindRepoRoot() . '/plugins/rutracker_check/trackers/tfile.php');

const TFILE_ORIGIN = 'https://tfile.example';
const TFILE_RETIRED_HOST = 'megatfile.cc';

function tfileReset($origin = TFILE_ORIGIN)
{
    ruTrackerChecker::reset();
    if ($origin !== '') {
        ruTrackerChecker::$accountOrigins['Tfile'] = $origin;
    }
    // The notice is latched per process, and one test process stands in for
    // many production cycles.
    strictSetPrivateStatic('TfileCheckImpl', 'unconfiguredLogged', false);
}

// The comment URL a tfile torrent carries. It is http on the torrents that
// exist, which is why the handler still matches it that way.
function tfileTopicUrl($id)
{
    return 'http://tfile.me/forum/viewtopic.php?p=' . $id;
}

function tfileViewtopicUrl($id, $origin = TFILE_ORIGIN)
{
    return $origin . '/forum/viewtopic.php?p=' . $id;
}

function tfileDownloadUrl($id, $origin = TFILE_ORIGIN)
{
    return $origin . '/forum/download.php?id=' . $id;
}

function tfileTopicBody($hash, $downloadId)
{
    return '<table><tr><td>Info hash:</td><td><strong>' . $hash . '</strong></td></tr></table>'
        . '<a href="download.php?id=' . $downloadId . '">torrent</a>';
}

// A parseable torrent and its hash, as the handler is given one.
function tfileTorrent($name)
{
    $raw = strictTorrentRaw($name, 'http://tfile.me/ann?uk=0000', tfileTopicUrl(1001));
    $torrent = @new Torrent($raw);
    strictAssertTrue(!$torrent->errors(), 'torrent fixture must parse');
    return array($raw, strtoupper((string) $torrent->hash_info()), $torrent);
}

$suite = new StrictTestSuite();

$suite->test('with no origin configured the handler asks nothing and says why', function () {
    tfileReset('');
    list($raw, $hash, $torrent) = tfileTorrent('current.mkv');

    $result = TfileCheckImpl::download_torrent(tfileTopicUrl(1001), $hash, $torrent);

    strictAssertSame(ruTrackerChecker::STE_NOT_NEED, $result,
        'a handler with no site to read has nothing to say about the torrent');
    strictAssertSame(array(), Snoopy::$requests,
        'and sends no request, least of all to a host nobody chose');
    $line = strictAssertOneLogMatching(ruTrackerChecker::$logs, 'tfileOrigin',
        'the reason is logged, naming the setting that carries the site');
    strictAssertEnglish($line, 'the notice is a log line');
});

$suite->test('the notice is said once however many topics come through', function () {
    tfileReset('');
    foreach (array('one.mkv', 'two.mkv', 'three.mkv') as $name) {
        list($raw, $hash, $torrent) = tfileTorrent($name);
        TfileCheckImpl::download_torrent(tfileTopicUrl(1001), $hash, $torrent);
    }
    strictAssertSame(1, count(strictLogsMatching(ruTrackerChecker::$logs, 'tfileOrigin')),
        'a cycle holding many tfile topics does not repeat the notice for each of them');
});

$suite->test('the topic is read on the configured origin', function () {
    tfileReset();
    list($raw, $hash, $torrent) = tfileTorrent('current.mkv');
    Snoopy::queue(tfileViewtopicUrl(1001), 200, tfileTopicBody($hash, 77));

    $result = TfileCheckImpl::download_torrent(tfileTopicUrl(1001), $hash, $torrent);

    strictAssertSame(ruTrackerChecker::STE_UPTODATE, $result, 'the topic still carries this hash');
    strictAssertSame(array(array('fetchComplex', tfileViewtopicUrl(1001))), Snoopy::$requests,
        'one request, on the configured origin');
});

$suite->test('the replacement is downloaded from the configured origin', function () {
    tfileReset();
    list($raw, $hash, $torrent) = tfileTorrent('old.mkv');
    list($newRaw, $newHash, $newTorrent) = tfileTorrent('new.mkv');
    strictAssertTrue($hash !== $newHash, 'the fixtures differ');
    Snoopy::queue(tfileViewtopicUrl(1001), 200, tfileTopicBody($newHash, 77));
    Snoopy::queue(tfileDownloadUrl(77), 200, $newRaw);
    ruTrackerChecker::$createResult = ruTrackerChecker::STE_UPDATED;

    $result = TfileCheckImpl::download_torrent(tfileTopicUrl(1001), $hash, $torrent);

    strictAssertSame(ruTrackerChecker::STE_UPDATED, $result, 'the newer torrent replaces the old one');
    strictAssertSame(
        array(
            array('fetchComplex', tfileViewtopicUrl(1001)),
            array('fetchComplex', tfileDownloadUrl(77)),
        ),
        Snoopy::$requests,
        'both the topic and the torrent come from the configured origin'
    );
    strictAssertSame(1, ruTrackerChecker::$createCalls, 'the answer became a torrent');
});

$suite->test('the retired domain is never asked for', function () {
    tfileReset();
    list($raw, $hash, $torrent) = tfileTorrent('old.mkv');
    list($newRaw, $newHash, $newTorrent) = tfileTorrent('new.mkv');
    Snoopy::queue(tfileViewtopicUrl(1001), 200, tfileTopicBody($newHash, 77));
    Snoopy::queue(tfileDownloadUrl(77), 200, $newRaw);
    ruTrackerChecker::$createResult = ruTrackerChecker::STE_UPDATED;

    TfileCheckImpl::download_torrent(tfileTopicUrl(1001), $hash, $torrent);

    foreach (Snoopy::$requests as $request) {
        strictAssertTrue(strpos($request[1], TFILE_RETIRED_HOST) === false,
            'the host the handler used to carry is not in ' . $request[1]);
    }
    strictAssertTrue(strpos(file_get_contents(testFindRepoRoot()
        . '/plugins/rutracker_check/trackers/tfile.php'), TFILE_RETIRED_HOST) === false,
        'and it is not written in the handler either');
});

// The cases above drive the handler against the fake ruTrackerChecker, whose
// accountOrigin() is a map. This one runs the real one, which reaches
// loginmgr: it is where the setting is read, where "https origin and nothing
// else" is decided, and where the form it comes back in is settled. It runs
// in its own process because the real loginmgr brings the real Snoopy with
// it, and the fake above holds that name here.
$suite->test('the real origin is the loginmgr Tfile account\'s, as loginmgr normalises it', function () {
    $root = testFindRepoRoot();
    $script = <<<'PHP'
<?php
$base = sys_get_temp_dir() . '/tfile-real-origin-' . getmypid();
mkdir($base . '/settings', 0700, true);
$_ENV['RU_PROFILE_PATH'] = $base;
$_ENV['RU_LOG_FILE'] = $base . '/application.log';
// check.php requires its siblings relative to the working directory, as the
// plugin's own entry points do.
chdir($argv[1] . '/plugins/rutracker_check');
require_once($argv[1] . '/plugins/rutracker_check/check.php');
// Loaded here rather than left to the first accountOrigin() call: including
// accounts.php evals the shipped loginmgr conf, which assigns '' to the
// setting below, and doing that after the loop had set it would measure the
// shipped default every time.
require_once($argv[1] . '/plugins/loginmgr/accounts.php');
foreach (array('HTTPS://Tfile.Example.:8443/', 'http://tfile.example', 'tfile.example', '') as $configured) {
    $GLOBALS['tfileOrigin'] = $configured;
    echo var_export(ruTrackerChecker::accountOrigin('Tfile'), true), "\n";
}
echo var_export(ruTrackerChecker::accountOrigin('NoSuchTracker'), true), "\n";
PHP;
    $file = sys_get_temp_dir() . '/tfile-real-origin-' . getmypid() . '.php';
    file_put_contents($file, $script);
    try {
        $output = array();
        $status = 0;
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' ' . escapeshellarg($root)
            . ' 2>&1', $output, $status);
        strictAssertSame(0, $status, 'the real resolution runs: ' . implode("\n", $output));
        strictAssertSame(
            array(
                "'https://tfile.example:8443'",
                "''",
                "''",
                "''",
                "''",
            ),
            $output,
            'a configured https origin comes back folded; http, a bare host and nothing come back empty, '
            . 'and so does a tracker loginmgr has no account for'
        );
    } finally {
        unlink($file);
    }
});

exit($suite->run());
