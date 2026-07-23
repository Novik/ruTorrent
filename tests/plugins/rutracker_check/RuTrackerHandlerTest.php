<?php

define('TESTLIB_HANDLER_STUBS', 1);
require_once(__DIR__ . '/TestLib.php');
require_once(testFindRepoRoot() . '/plugins/rutracker_check/trackers/rutracker.php');

function ruApiUrl($topicId)
{
    return 'https://api.rutracker.cc/v1/get_tor_hash?by=topic_id&val=' . $topicId;
}

function ruDownloadUrl($topicId)
{
    return 'https://rutracker.org/forum/dl.php?t=' . $topicId;
}

function ruTopicUrl($topicId, $start = null)
{
    $url = 'https://rutracker.org/forum/viewtopic.php?t=' . $topicId;
    return $start === null ? $url : $url . '&start=' . $start;
}

function ruUserPost($topicId)
{
    return strictCp1251(
        '<table class="topic"><tbody id="post_100" class="row1"><tr>'
        . '<td class="poster_info td1"><p class="nick nick-author">ordinary-user</p>'
        . '<p class="rank_img"><img class="user-rank" alt="User"></p></td>'
        . '<td><div class="post_body"><a href="viewtopic.php?t=' . $topicId . '">other topic</a>'
        . ' Этот фильм было Поглощено вниманием зрителей.</div><!--/post_body--></td></tr></tbody></table>'
    );
}

function ruModeratorPost($topicId, $prefix = '')
{
    return strictCp1251(
        '<table class="topic">' . $prefix . '<tbody id="post_200" class="row1"><tr>'
        . '<td class="poster_info td1"><p class="nick nick-author">tracker-moderator</p>'
        . '<p class="rank_img"><img class="user-rank" alt="Moderator"></p></td>'
        . '<td><div class="post_body"><a class="postLink" href="viewtopic.php?t=' . $topicId . '">replacement</a>'
        . '<span class="post-b">Поглощено</span></div><!--/post_body--></td></tr></tbody></table>'
    );
}

$suite = new StrictTestSuite();
$oldHash = str_repeat('A', 40);
$newHash = str_repeat('B', 40);

$suite->test('error pages and ambiguous topics classify without createTorrent', function () use ($oldHash, $newHash) {
    $ambiguous = strictCp1251(
        '<table class="topic"><tbody id="post_200" class="row1"><tr>'
        . '<td><img class="user-rank" alt="Moderator"></td><td><div class="post_body">'
        . '<a href="viewtopic.php?t=98">related</a><a href="viewtopic.php?t=99">replacement</a>'
        . '<span>Поглощено</span></div><!--/post_body--></td></tr></tbody></table>'
    );
    $rows = array(
        array(
            'label' => 'API object without a hash falls back without TypeError',
            'responses' => array(
                array(ruApiUrl(42), 200, json_encode(array('result' => array('42' => array('error_code' => 1))))),
                array(ruDownloadUrl(42), 200, '<html>attachment data not found</html>'),
                array(ruTopicUrl(42), 200, strictCp1251('<div class="post_body">Topic unavailable</div>')),
            ),
            'expected' => ruTrackerChecker::STE_DELETED,
        ),
        array(
            'label' => 'concrete remote hash plus login page is a reachability error',
            'responses' => array(
                array(ruApiUrl(42), 200, json_encode(array('result' => array('42' => $newHash)))),
                array(ruDownloadUrl(42), 200, '<!DOCTYPE html><html><form><input name="login_password"></form></html>'),
                array(ruTopicUrl(42), 200, strictCp1251('<div class="post_body">Active topic</div>')),
            ),
            'expected' => ruTrackerChecker::STE_CANT_REACH_TRACKER,
        ),
        array(
            'label' => 'ordinary user text containing Pogloshcheno does not trigger replacement',
            'responses' => array(
                array(ruApiUrl(42), 500, ''),
                array(ruDownloadUrl(42), 200, '<html>attachment data not found</html>'),
                array(ruTopicUrl(42), 200, ruUserPost(99)),
                // Canary: if the user link were followed, this junk payload
                // would turn the result into STE_ERROR and fail the row.
                array(ruDownloadUrl(99), 200, 'wrong-user-triggered-replacement'),
            ),
            'expected' => ruTrackerChecker::STE_CANT_REACH_TRACKER,
        ),
        array(
            'label' => 'ambiguous moderator absorption links do not select a replacement',
            'responses' => array(
                array(ruApiUrl(42), 500, ''),
                array(ruDownloadUrl(42), 200, '<html>attachment data not found</html>'),
                array(ruTopicUrl(42), 200, $ambiguous),
            ),
            'expected' => ruTrackerChecker::STE_CANT_REACH_TRACKER,
        ),
        array(
            'label' => 'HTML error from replacement topic is not passed to createTorrent',
            'responses' => array(
                array(ruApiUrl(42), 500, ''),
                array(ruDownloadUrl(42), 200, '<html>attachment data not found</html>'),
                array(ruTopicUrl(42), 200, ruModeratorPost(99)),
                array(ruDownloadUrl(99), 200, 'Error: attachment data not found'),
            ),
            'expected' => ruTrackerChecker::STE_CANT_REACH_TRACKER,
        ),
    );

    foreach ($rows as $row) {
        ruTrackerChecker::reset();
        foreach ($row['responses'] as $response) {
            Snoopy::queue($response[0], $response[1], $response[2]);
        }

        $result = RuTrackerCheckImpl::download_torrent(
            'https://rutracker.org/forum/viewtopic.php?t=42',
            $oldHash,
            null
        );

        strictAssertSame($row['expected'], $result, $row['label'] . ': classification result');
        strictAssertSame(0, count(ruTrackerChecker::$created), $row['label'] . ': junk must never reach createTorrent');
    }
});

$suite->test('final exact moderator absorption notice triggers replacement', function () use ($oldHash) {
    ruTrackerChecker::reset();
    Snoopy::queue(ruApiUrl(42), 500, '');
    Snoopy::queue(ruDownloadUrl(42), 200, '<html>attachment data not found</html>');
    $ordinary = '<tbody id="post_150" class="row1"><tr><td class="poster_info td1"><p class="rank_img">User</p></td>'
        . '<td><div class="post_body">Earlier unrelated post</div><!--/post_body--></td></tr></tbody>';
    Snoopy::queue(ruTopicUrl(42), 200, ruModeratorPost(99, $ordinary));
    $replacementPayload = strictTorrentRaw('replacement.mkv', 'http://tracker.example/announce');
    Snoopy::queue(ruDownloadUrl(99), 200, $replacementPayload);

    $result = RuTrackerCheckImpl::download_torrent(
        'https://rutracker.org/forum/viewtopic.php?t=42',
        $oldHash,
        null
    );

    strictAssertSame(null, $result, 'Successful replacement propagates createTorrent result');
    strictAssertSame(1, count(ruTrackerChecker::$created), 'Exact final moderator notice triggers one replacement');
    strictAssertSame(
        $replacementPayload,
        ruTrackerChecker::$created[0]['payload'],
        'Replacement topic payload is forwarded unchanged'
    );
});

$suite->test('unparseable absorbed replacement payload is an error without createTorrent', function () use ($oldHash) {
    ruTrackerChecker::reset();
    Snoopy::queue(ruApiUrl(42), 500, '');
    Snoopy::queue(ruDownloadUrl(42), 200, '<html>attachment data not found</html>');
    Snoopy::queue(ruTopicUrl(42), 200, ruModeratorPost(99));
    // Passes looksLikeHtmlError (no leading '<', no error phrase) but is not
    // parseable metainfo: pre-validation must stop it before createTorrent.
    Snoopy::queue(ruDownloadUrl(99), 200, "junk\x00payload that is not bencoded metainfo");

    $result = RuTrackerCheckImpl::download_torrent(
        'https://rutracker.org/forum/viewtopic.php?t=42',
        $oldHash,
        null
    );

    strictAssertSame(ruTrackerChecker::STE_ERROR, $result, 'Unparseable replacement download is a hard error');
    strictAssertSame(0, count(ruTrackerChecker::$created), 'Unparseable replacement must never reach createTorrent');
});

$suite->test('last page ignores same-topic start links inside post bodies', function () use ($oldHash) {
    ruTrackerChecker::reset();
    Snoopy::queue(ruApiUrl(42), 500, '');
    Snoopy::queue(ruDownloadUrl(42), 200, '<html>attachment data not found</html>');
    $firstPage = strictCp1251(
        '<a class="pg" href="viewtopic.php?t=42&amp;start=50">2</a>'
        . '<tbody id="post_100"><tr><td><div class="post_body">'
        . '<a href="viewtopic.php?t=42&amp;start=999999">stale user link</a>'
        . '</div><!--/post_body--></td></tr></tbody>'
    );
    Snoopy::queue(ruTopicUrl(42), 200, $firstPage);
    Snoopy::queue(ruTopicUrl(42, 50), 200, ruModeratorPost(99));
    $replacementPayload = strictTorrentRaw('replacement-last-page.mkv', 'http://tracker.example/announce');
    Snoopy::queue(ruDownloadUrl(99), 200, $replacementPayload);

    $result = RuTrackerCheckImpl::download_torrent(
        'https://rutracker.org/forum/viewtopic.php?t=42',
        $oldHash,
        null
    );

    strictAssertSame(null, $result, 'real pagination leads to the final moderator absorption notice');
    strictAssertSame(1, count(ruTrackerChecker::$created), 'only the replacement from the real last page is used');
});

$suite->test('valid metainfo containing Error text is not mistaken for an HTTP error', function () use ($oldHash, $newHash) {
    ruTrackerChecker::reset();
    ruTrackerChecker::$createResult = ruTrackerChecker::STE_UPDATED;
    $payload = strictTorrentRaw('Error: valid release.mkv', 'http://tracker.example/announce');
    Snoopy::queue(ruApiUrl(42), 200, json_encode(array('result' => array('42' => $newHash))));
    Snoopy::queue(ruDownloadUrl(42), 200, $payload);

    $result = RuTrackerCheckImpl::download_torrent(
        'https://rutracker.org/forum/viewtopic.php?t=42',
        $oldHash,
        null
    );

    strictAssertSame(ruTrackerChecker::STE_UPDATED, $result, 'valid binary metainfo must reach createTorrent');
    strictAssertSame(1, count(ruTrackerChecker::$created), 'valid metainfo should be submitted exactly once');
});

$suite->test('valid direct metainfo transaction error does not trigger a second fallback', function () use ($oldHash, $newHash) {
    ruTrackerChecker::reset();
    ruTrackerChecker::$createResult = ruTrackerChecker::STE_ERROR;
    $payload = strictTorrentRaw('replacement.mkv', 'http://tracker.example/announce');
    Snoopy::queue(ruApiUrl(42), 200, json_encode(array('result' => array('42' => $newHash))));
    Snoopy::queue(ruDownloadUrl(42), 200, $payload);

    $result = RuTrackerCheckImpl::download_torrent(
        'https://rutracker.org/forum/viewtopic.php?t=42',
        $oldHash,
        null
    );

    strictAssertSame(ruTrackerChecker::STE_ERROR, $result, 'local replacement failure is returned unchanged');
    strictAssertSame(1, count(ruTrackerChecker::$created), 'valid direct metainfo is submitted only once');
    strictAssertSame(2, count(Snoopy::$requests), 'transaction failure must not fetch the topic for another replacement');
});

exit($suite->run());
