<?php

/**
 * AniDUB handler: the site it reads has to be the site the account names.
 *
 * The handler reads a topic page and hands the answer to createTorrent(),
 * which replaces the torrent rtorrent is running, so both of its requests
 * want the session the loginmgr AniDUB account holds. Snoopy asks
 * accountManager::getAccount() which account claims a URL, and
 * commonAccount::test() refuses one whose scheme is weaker than its site's
 * -- the AniDUB account names https -- so an http request selects no
 * account at all and the handler reads what a guest reads, with no
 * diagnostic of any kind.
 *
 * Nothing here reaches the network: the Snoopy in TestLib answers only
 * queued URLs and throws on any other, which is what asserts that neither
 * request is made in the clear.
 */

define('TESTLIB_HANDLER_STUBS', 1);
require_once(__DIR__ . '/TestLib.php');
require_once(testFindRepoRoot() . '/plugins/rutracker_check/trackers/anidub.php');

const ANIDUB_SITE = 'https://tr.anidub.com';
const ANIDUB_NEWSID = 4242;
const ANIDUB_DOWNLOAD = '/engine/download.php?id=99';

// The comment URL an anidub torrent carries. It is http on the torrents that
// exist, which is why the handler still matches it that way.
function anidubCommentUrl($id = ANIDUB_NEWSID)
{
    return 'http://tr.anidub.com/?newsid=' . $id;
}

function anidubTopicUrl($id = ANIDUB_NEWSID)
{
    return ANIDUB_SITE . '/?newsid=' . $id;
}

function anidubDownloadUrl()
{
    return ANIDUB_SITE . ANIDUB_DOWNLOAD;
}

/**
 * The topic page, as the handler reads it: the quality tag of the torrent's
 * own name names the block, and the block carries the download link. The
 * handler strips line terminators before matching, so the whitespace its
 * pattern requires is written as spaces.
 */
function anidubTopicBody($quality = 'tv720', $href = ANIDUB_DOWNLOAD)
{
    return '<html><div id="' . $quality . '"><div id=\'t1\'> <div class="torrent_h"> '
        . '<a href="' . $href . '" title="torrent">get</a></div></div></div></html>';
}

// A parseable torrent whose name carries the quality tag the handler reads.
function anidubTorrent($name = 'Anime_[720p]_ep01.mkv')
{
    $raw = strictTorrentRaw($name, 'http://tr.anidub.com/ann?uk=0000', anidubCommentUrl());
    $torrent = @new Torrent($raw);
    strictAssertTrue(!$torrent->errors(), 'torrent fixture must parse');
    return array($raw, strtoupper((string) $torrent->hash_info()), $torrent);
}

$suite = new StrictTestSuite();

$suite->test('the topic is read on the site the account names', function () {
    ruTrackerChecker::reset();
    list($raw, $hash, $torrent) = anidubTorrent();
    Snoopy::queue(anidubTopicUrl(), 200, anidubTopicBody());
    Snoopy::queue(anidubDownloadUrl(), 200, $raw);
    ruTrackerChecker::$createResult = ruTrackerChecker::STE_UPTODATE;

    $result = AniDUBCheckImpl::download_torrent(anidubCommentUrl(), $hash, $torrent);

    strictAssertSame(ruTrackerChecker::STE_UPTODATE, $result, 'the topic answered for this torrent');
    strictAssertSame(
        array(
            array('fetchComplex', anidubTopicUrl()),
            array('fetchComplex', anidubDownloadUrl()),
        ),
        Snoopy::$requests,
        'both the topic and the torrent are asked for over https'
    );
    strictAssertSame(1, ruTrackerChecker::$createCalls, 'the answer became a torrent');
});

$suite->test('no request is made in the clear', function () {
    ruTrackerChecker::reset();
    list($raw, $hash, $torrent) = anidubTorrent();
    Snoopy::queue(anidubTopicUrl(), 200, anidubTopicBody());
    Snoopy::queue(anidubDownloadUrl(), 200, $raw);
    ruTrackerChecker::$createResult = ruTrackerChecker::STE_UPDATED;

    AniDUBCheckImpl::download_torrent(anidubCommentUrl(), $hash, $torrent);

    strictAssertTrue(count(Snoopy::$requests) > 0, 'the handler asked for something');
    foreach (Snoopy::$requests as $request) {
        strictAssertTrue(stripos($request[1], 'http://') !== 0,
            'an http request carries no account, so it is not one this handler makes: ' . $request[1]);
    }
});

$suite->test('the comment url a torrent already carries is still matched', function () {
    ruTrackerChecker::reset();
    list($raw, $hash, $torrent) = anidubTorrent();
    Snoopy::queue(anidubTopicUrl(), 200, anidubTopicBody());
    Snoopy::queue(anidubDownloadUrl(), 200, $raw);
    ruTrackerChecker::$createResult = ruTrackerChecker::STE_UPTODATE;

    // Where the site is read is a separate question from how an existing
    // torrent identifies itself, and the torrents that exist say http.
    $result = AniDUBCheckImpl::download_torrent(anidubCommentUrl(), $hash, $torrent);

    strictAssertSame(ruTrackerChecker::STE_UPTODATE, $result,
        'an http comment url still reaches the handler');
});

$suite->test('the topic the comment url names is the topic read', function () {
    ruTrackerChecker::reset();
    list($raw, $hash, $torrent) = anidubTorrent();
    Snoopy::queue(anidubTopicUrl(7), 200, anidubTopicBody());
    Snoopy::queue(anidubDownloadUrl(), 200, $raw);
    ruTrackerChecker::$createResult = ruTrackerChecker::STE_UPTODATE;

    AniDUBCheckImpl::download_torrent(anidubCommentUrl(7), $hash, $torrent);

    strictAssertSame(array('fetchComplex', anidubTopicUrl(7)), Snoopy::$requests[0],
        'the news id of the comment url is the one asked for');
});

$suite->test('a torrent with no quality tag is not looked up at all', function () {
    ruTrackerChecker::reset();
    list($raw, $hash, $torrent) = anidubTorrent('Anime_ep01.mkv');

    $result = AniDUBCheckImpl::download_torrent(anidubCommentUrl(), $hash, $torrent);

    strictAssertSame(ruTrackerChecker::STE_NOT_NEED, $result,
        'the handler has no way to pick a torrent without one');
    strictAssertSame(array(), Snoopy::$requests, 'and asks for nothing');
});

$suite->test('a url on another host is not this handler\'s', function () {
    ruTrackerChecker::reset();
    list($raw, $hash, $torrent) = anidubTorrent();

    $result = AniDUBCheckImpl::download_torrent('http://evil.test/x/tr.anidub.com/?newsid=1',
        $hash, $torrent);

    strictAssertSame(ruTrackerChecker::STE_NOT_NEED, $result, 'the handler declines it');
    strictAssertSame(array(), Snoopy::$requests, 'and asks nobody about it');
});

exit($suite->run());
