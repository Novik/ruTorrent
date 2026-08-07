<?php

/**
 * Kinozal handler: every answer that only proves "could not check" must stay
 * retryable, and only the tracker's own "no such torrent" may end as a
 * deletion. Fixtures are the bodies the live site returned on 2026-08-07.
 */

define('TESTLIB_HANDLER_STUBS', 1);
require_once(__DIR__ . '/TestLib.php');
require_once(testFindRepoRoot() . '/plugins/rutracker_check/trackers/kinozal.php');

function kinozalReset()
{
    ruTrackerChecker::reset();
}

function kinozalTopicUrl($id)
{
    return 'https://kinozal.me/details.php?id=' . $id;
}

function kinozalDetailsUrl($id)
{
    return 'https://kinozal.guru/get_srv_details.php?action=2&id=' . $id;
}

function kinozalDownloadUrl($id)
{
    return 'https://dl.kinozal.guru/download.php?id=' . $id;
}

// get_srv_details.php answers in UTF-8 (Content-Type: text/html; charset=UTF-8)
// even though the rest of the site is windows-1251.
function kinozalDetailsBody($hash)
{
    return '<ul><li>Инфо хеш: ' . $hash . '</li><li>Размер части торрента: 2 МБ</li>'
        . '<li><div class=\'b ing\'>movie.mkv <i>26.75 ГБ (28721509590)</i></div></li></ul>';
}

function kinozalUnauthorizedBody()
{
    return 'Вы не зарегистрированный пользователь или не авторизированы, чтобы '
        . 'зарегистрироваться пройдите <a href=\'/signup.php\' class=\'sba\'>сюда</a>.';
}

function kinozalMissingBody()
{
    return 'Торрент файл не найден.';
}

function kinozalLoginPage()
{
    return '<ul class=lis><li class=mn><a href="/login.php">Вход</a></li>'
        . '<li><a href="/signup.php">Регистрация в Кинозал.GURU</a></li></ul>'
        . '<form method=post action="/takelogin.php">'
        . '<input type=password size=35 id="password" name="password" value=""></form>';
}

// Builds a parseable Kinozal torrent plus its hash and topic URL.
function kinozalTorrent($name, $id)
{
    $raw = strictTorrentRaw($name, 'http://tr2.torrent4me.com/ann?uk=K0I5ZrJ6If1', kinozalTopicUrl($id));
    $torrent = @new Torrent($raw);
    strictAssertTrue(!$torrent->errors(), 'torrent fixture must parse');
    return array($raw, (string) $torrent->hash_info(), $torrent);
}

$suite = new StrictTestSuite();

$suite->test('a guest answer from the details endpoint is a reachability error', function () {
    kinozalReset();
    list($raw, $hash, $torrent) = kinozalTorrent('current.mkv', 2148020);
    Snoopy::queue(kinozalDetailsUrl(2148020), 200, kinozalUnauthorizedBody());

    $result = KinozalCheckImpl::download_torrent(kinozalTopicUrl(2148020), $hash, $torrent);

    strictAssertSame(ruTrackerChecker::STE_CANT_REACH_TRACKER, $result,
        'a login wall proves nothing about the topic');
    strictAssertSame(
        array(array('fetchComplex', kinozalDetailsUrl(2148020))),
        Snoopy::$requests,
        'the chain stops at the details request'
    );
    strictAssertSame(0, ruTrackerChecker::$createCalls, 'the replacement path is never entered');
});

$suite->test('the login page served with status 200 is a reachability error', function () {
    kinozalReset();
    list($raw, $hash, $torrent) = kinozalTorrent('current.mkv', 2148020);
    Snoopy::queue(kinozalDetailsUrl(2148020), 200, kinozalLoginPage());

    $result = KinozalCheckImpl::download_torrent(kinozalTopicUrl(2148020), $hash, $torrent);

    strictAssertSame(ruTrackerChecker::STE_CANT_REACH_TRACKER, $result,
        'a followed redirect that lands on login.php is not a verdict');
    strictAssertSame(1, count(Snoopy::$requests), 'the chain stops at the details request');
});

$suite->test('the tracker\'s own "no such torrent" is a deletion', function () {
    kinozalReset();
    list($raw, $hash, $torrent) = kinozalTorrent('gone.mkv', 2148020);
    Snoopy::queue(kinozalDetailsUrl(2148020), 200, kinozalMissingBody());

    $result = KinozalCheckImpl::download_torrent(kinozalTopicUrl(2148020), $hash, $torrent);

    strictAssertSame(ruTrackerChecker::STE_DELETED, $result,
        'an authenticated "not found" is the only authoritative deletion signal');
    strictAssertSame(1, count(Snoopy::$requests), 'a deleted topic needs no download attempt');
});

$suite->test('a windows-1251 "no such torrent" answer is recognised too', function () {
    kinozalReset();
    list($raw, $hash, $torrent) = kinozalTorrent('gone-cp1251.mkv', 2148020);
    Snoopy::queue(kinozalDetailsUrl(2148020), 200, strictCp1251(kinozalMissingBody()));

    $result = KinozalCheckImpl::download_torrent(kinozalTopicUrl(2148020), $hash, $torrent);

    strictAssertSame(ruTrackerChecker::STE_DELETED, $result,
        'the site\'s own legacy charset must not hide the deletion signal');
});

$suite->test('a matching info hash is up to date without a download', function () {
    kinozalReset();
    list($raw, $hash, $torrent) = kinozalTorrent('current.mkv', 2148020);
    Snoopy::queue(kinozalDetailsUrl(2148020), 200, kinozalDetailsBody($hash));

    $result = KinozalCheckImpl::download_torrent(kinozalTopicUrl(2148020), $hash, $torrent);

    strictAssertSame(ruTrackerChecker::STE_UPTODATE, $result, 'the tracker still lists our hash');
    strictAssertSame(
        array(array('fetchComplex', kinozalDetailsUrl(2148020))),
        Snoopy::$requests,
        'an up-to-date topic is never downloaded'
    );
});

$suite->test('a changed info hash hands valid metainfo to the replacement', function () {
    kinozalReset();
    list($oldRaw, $oldHash, $oldTorrent) = kinozalTorrent('old.mkv', 2148020);
    list($newRaw, $newHash, $newTorrent) = kinozalTorrent('new.mkv', 2148020);
    strictAssertTrue($oldHash !== $newHash, 'the fixtures must represent an update');

    Snoopy::queue(kinozalDetailsUrl(2148020), 200, kinozalDetailsBody($newHash));
    Snoopy::queue(kinozalDownloadUrl(2148020), 200, $newRaw);

    $result = KinozalCheckImpl::download_torrent(kinozalTopicUrl(2148020), $oldHash, $oldTorrent);

    strictAssertSame(null, $result, 'a successful replacement propagates createTorrent\'s result');
    strictAssertSame(1, ruTrackerChecker::$createCalls, 'the new torrent is handed over once');
    strictAssertSame($newRaw, ruTrackerChecker::$created[0]['payload'], 'the downloaded bytes are passed through');
    strictAssertSame($oldHash, ruTrackerChecker::$created[0]['old_hash'], 'the replacement targets the old hash');
});

$suite->test('a download redirected to the login page is a reachability error', function () {
    kinozalReset();
    list($oldRaw, $oldHash, $oldTorrent) = kinozalTorrent('old.mkv', 2148020);
    list($newRaw, $newHash, $newTorrent) = kinozalTorrent('new.mkv', 2148020);

    Snoopy::queue(kinozalDetailsUrl(2148020), 200, kinozalDetailsBody($newHash));
    // What dl.kinozal.guru answers without a session, as seen when the
    // redirect chain does not end in a 200: the 302 itself, with the
    // login.php Location it carries on the live site.
    Snoopy::queue(kinozalDownloadUrl(2148020), 302, '',
        array('Location: //kinozal.guru/login.php?to=%2Fdownload.php%3Fid%3D2148020'));

    $result = KinozalCheckImpl::download_torrent(kinozalTopicUrl(2148020), $oldHash, $oldTorrent);

    strictAssertSame(ruTrackerChecker::STE_CANT_REACH_TRACKER, $result,
        'a redirect to the login wall is not proof of deletion');
    strictAssertSame(0, ruTrackerChecker::$createCalls, 'createTorrent is never reached');
});

$suite->test('a login page instead of a torrent is a reachability error', function () {
    kinozalReset();
    list($oldRaw, $oldHash, $oldTorrent) = kinozalTorrent('old.mkv', 2148020);
    list($newRaw, $newHash, $newTorrent) = kinozalTorrent('new.mkv', 2148020);

    Snoopy::queue(kinozalDetailsUrl(2148020), 200, kinozalDetailsBody($newHash));
    Snoopy::queue(kinozalDownloadUrl(2148020), 200, kinozalLoginPage());

    $result = KinozalCheckImpl::download_torrent(kinozalTopicUrl(2148020), $oldHash, $oldTorrent);

    strictAssertSame(ruTrackerChecker::STE_CANT_REACH_TRACKER, $result,
        'HTML where metainfo was expected is not proof of deletion');
    strictAssertSame(0, ruTrackerChecker::$createCalls,
        'createTorrent\'s "unparseable means deleted" contract is never invoked');
});

$suite->test('an unparseable download body is a reachability error', function () {
    kinozalReset();
    list($oldRaw, $oldHash, $oldTorrent) = kinozalTorrent('old.mkv', 2148020);
    list($newRaw, $newHash, $newTorrent) = kinozalTorrent('new.mkv', 2148020);

    Snoopy::queue(kinozalDetailsUrl(2148020), 200, kinozalDetailsBody($newHash));
    Snoopy::queue(kinozalDownloadUrl(2148020), 200, 'not a torrent at all');

    $result = KinozalCheckImpl::download_torrent(kinozalTopicUrl(2148020), $oldHash, $oldTorrent);

    strictAssertSame(ruTrackerChecker::STE_CANT_REACH_TRACKER, $result,
        'bytes that are not metainfo are validated before the replacement');
    strictAssertSame(0, ruTrackerChecker::$createCalls, 'nothing is handed over');
});

$suite->test('an empty download body is a reachability error', function () {
    kinozalReset();
    list($oldRaw, $oldHash, $oldTorrent) = kinozalTorrent('old.mkv', 2148020);
    list($newRaw, $newHash, $newTorrent) = kinozalTorrent('new.mkv', 2148020);

    Snoopy::queue(kinozalDetailsUrl(2148020), 200, kinozalDetailsBody($newHash));
    Snoopy::queue(kinozalDownloadUrl(2148020), 200, '');

    $result = KinozalCheckImpl::download_torrent(kinozalTopicUrl(2148020), $oldHash, $oldTorrent);

    strictAssertSame(ruTrackerChecker::STE_CANT_REACH_TRACKER, $result, 'an empty body carries no verdict');
    strictAssertSame(0, ruTrackerChecker::$createCalls, 'nothing is handed over');
});

$suite->test('a transport failure is a reachability error', function () {
    kinozalReset();
    list($raw, $hash, $torrent) = kinozalTorrent('current.mkv', 2148020);
    // The https path stores curl's exit code (6 = DNS failure) as the status.
    Snoopy::queue(kinozalDetailsUrl(2148020), 6, '');

    $result = KinozalCheckImpl::download_torrent(kinozalTopicUrl(2148020), $hash, $torrent);

    strictAssertSame(ruTrackerChecker::STE_CANT_REACH_TRACKER, $result, 'a dead socket is retryable');
});

$suite->test('a server error on the details endpoint is a reachability error', function () {
    kinozalReset();
    list($raw, $hash, $torrent) = kinozalTorrent('current.mkv', 2148020);
    Snoopy::queue(kinozalDetailsUrl(2148020), 503, '<html>maintenance</html>');

    $result = KinozalCheckImpl::download_torrent(kinozalTopicUrl(2148020), $hash, $torrent);

    strictAssertSame(ruTrackerChecker::STE_CANT_REACH_TRACKER, $result,
        'an HTTP error is never a deletion');
});

$suite->test('every Kinozal mirror in the comment is handled', function () {
    foreach (array('kinozal.tv', 'kinozal.me', 'kinozal.guru') as $host) {
        kinozalReset();
        list($raw, $hash, $torrent) = kinozalTorrent('current.mkv', 2148020);
        $url = 'https://' . $host . '/details.php?id=2148020';
        Snoopy::queue(kinozalDetailsUrl(2148020), 200, kinozalDetailsBody($hash));

        $result = KinozalCheckImpl::download_torrent($url, $hash, $torrent);

        strictAssertSame(ruTrackerChecker::STE_UPTODATE, $result, $host . ' must be recognised');
    }
});

$suite->test('a URL this handler does not own triggers no request', function () {
    kinozalReset();
    list($raw, $hash, $torrent) = kinozalTorrent('current.mkv', 2148020);

    $result = KinozalCheckImpl::download_torrent('http://tr2.torrent4me.com/ann?uk=K0I5ZrJ6If1', $hash, $torrent);

    strictAssertSame(ruTrackerChecker::STE_NOT_NEED, $result, 'an announce URL carries no topic id');
    strictAssertSame(0, count(Snoopy::$requests), 'no request is made');
});

exit($suite->run());
