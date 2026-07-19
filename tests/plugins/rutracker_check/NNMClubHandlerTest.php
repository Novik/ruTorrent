<?php

define('TESTLIB_HANDLER_STUBS', 1);
require_once(__DIR__ . '/TestLib.php');
require_once(testFindRepoRoot() . '/plugins/rutracker_check/trackers/nnmclub.php');

function nnmReset()
{
    ruTrackerChecker::reset();
    strictSetPrivateStatic('NNMClubCheckImpl', 'donor', false);
    rTorrentSettings::get()->session = '/nonexistent/';
}

function nnmDynamicScrapeUrl($host, $passkey, $hash)
{
    return 'http://' . $host . '/' . $passkey
        . '/scrape?info_hash=' . rawurlencode(hex2bin($hash));
}

function nnmStaticScrapeUrl($host, $passkey, $hash)
{
    return 'http://' . $host . '/scrape?uk=' . rawurlencode($passkey)
        . '&info_hash=' . rawurlencode(hex2bin($hash));
}

function nnmTopicUrl($topicId)
{
    return 'https://nnmclub.to/forum/viewtopic.php?t=' . $topicId;
}

function nnmDownloadUrl($downloadId)
{
    return 'https://nnmclub.to/forum/download.php?id=' . $downloadId;
}

$suite = new StrictTestSuite();
$realPasskey = 'AbCdEf0123456789AbCdEf0123456789';
$dummyPasskey = str_repeat('f', 32);

$suite->test('scrape hit returns up to date without guest request', function () use ($realPasskey) {
    $rows = array(
        array(
            'label' => 'path-style credential on official tracker host',
            'name' => 'current.bin',
            'announce' => 'http://bt02.nnm-club.cc:2710/' . $realPasskey . '/announce',
            'comment' => nnmTopicUrl(42),
            'url' => nnmTopicUrl(42),
            'scrapeHost' => 'bt02.nnm-club.cc:2710',
            'scrapeMode' => 'dynamic',
        ),
        array(
            'label' => 'announce-only torrent is confirmed by its tracker scrape',
            'name' => 'announce-only.bin',
            'announce' => 'http://bt.searchtor.to/announce?uk=' . $realPasskey,
            'comment' => '',
            'url' => 'http://bt.searchtor.to/announce?uk=' . $realPasskey,
            'scrapeHost' => 'bt.searchtor.to',
            'scrapeMode' => 'static',
        ),
        array(
            'label' => 'documented legacy static tracker host bt.nnm-club.ru',
            'name' => 'legacy-ru.bin',
            'announce' => 'http://bt.nnm-club.ru:2710/announce?uk=' . $realPasskey,
            'comment' => nnmTopicUrl(42),
            'url' => nnmTopicUrl(42),
            'scrapeHost' => 'bt.nnm-club.ru:2710',
            'scrapeMode' => 'static',
        ),
        array(
            'label' => 'documented legacy static tracker host nnm-club.info',
            'name' => 'legacy-info.bin',
            'announce' => 'http://nnm-club.info:2710/announce?uk=' . $realPasskey,
            'comment' => nnmTopicUrl(42),
            'url' => nnmTopicUrl(42),
            'scrapeHost' => 'nnm-club.info:2710',
            'scrapeMode' => 'static',
        ),
        array(
            'label' => 'current searchtor dynamic credential scrapes its own hash',
            'name' => 'current-searchtor.bin',
            'announce' => 'http://bt.searchtor.to/' . $realPasskey . '/announce',
            'comment' => nnmTopicUrl(42),
            'url' => nnmTopicUrl(42),
            'scrapeHost' => 'bt.searchtor.to',
            'scrapeMode' => 'dynamic',
        ),
        array(
            'label' => 'www topic host and static searchtor credential',
            'name' => 'www-topic.bin',
            'announce' => 'http://bt.searchtor.to/announce?uk=' . $realPasskey,
            'comment' => 'https://www.nnmclub.to/forum/viewtopic.php?t=42',
            'url' => 'https://www.nnmclub.to/forum/viewtopic.php?t=42',
            'scrapeHost' => 'bt.searchtor.to',
            'scrapeMode' => 'static',
        ),
    );

    foreach ($rows as $row) {
        nnmReset();
        $raw = strictTorrentRaw(
            $row['name'],
            $row['announce'],
            $row['comment'],
            isset($row['announceList']) ? $row['announceList'] : null
        );
        $torrent = @new Torrent($raw);
        strictAssertTrue(!$torrent->errors(), $row['label'] . ': fixture must parse');
        $hash = $torrent->hash_info();
        $scrapeUrl = $row['scrapeMode'] === 'dynamic'
            ? nnmDynamicScrapeUrl($row['scrapeHost'], $realPasskey, $hash)
            : nnmStaticScrapeUrl($row['scrapeHost'], $realPasskey, $hash);
        Snoopy::queue($scrapeUrl, 200, strictScrapePayload($hash, true));

        $result = NNMClubCheckImpl::download_torrent($row['url'], $hash, $torrent);

        strictAssertSame(ruTrackerChecker::STE_UPTODATE, $result, $row['label'] . ': scrape hit is up to date');
        strictAssertSame(
            array(array('fetchComplex', $scrapeUrl)),
            Snoopy::$requests,
            $row['label'] . ': exactly the expected scrape request, no guest request'
        );
        strictAssertSame(0, count(ruTrackerChecker::$created), $row['label'] . ': up-to-date torrent is not replaced');
    }
});

$suite->test('scrape miss downloads guest torrent and patches real passkey', function () use ($realPasskey, $dummyPasskey) {
    nnmReset();
    $oldRaw = strictTorrentRaw(
        'old.bin',
        'http://bt.searchtor.to/announce?uk=' . $realPasskey,
        nnmTopicUrl(42)
    );
    $oldTorrent = @new Torrent($oldRaw);
    strictAssertTrue(!$oldTorrent->errors(), 'Old torrent fixture must parse');
    $oldHash = $oldTorrent->hash_info();

    $guestRaw = strictTorrentRaw(
        'new.bin',
        'http://bt.searchtor.to/' . $dummyPasskey . '/announce',
        nnmTopicUrl(42),
        array(
            array('http://ipv6.bt.searchtor.to/' . $dummyPasskey . '/announce'),
            array('http://bt.nnmclub.example/' . $dummyPasskey . '/announce'),
            array('https://example.test/announce'),
        )
    );
    $guestTorrent = @new Torrent($guestRaw);
    strictAssertTrue(!$guestTorrent->errors(), 'Guest torrent fixture must parse');
    $guestHash = $guestTorrent->hash_info();
    strictAssertTrue($guestHash !== $oldHash, 'Guest fixture must represent an update');

    Snoopy::queue(
        nnmStaticScrapeUrl('bt.searchtor.to', $realPasskey, $oldHash),
        200,
        strictScrapePayload($oldHash, false)
    );
    Snoopy::queue(nnmTopicUrl(42), 200, '<a href="download.php?id=7">download</a>');
    Snoopy::queue(nnmDownloadUrl(7), 200, $guestRaw);

    $result = NNMClubCheckImpl::download_torrent(nnmTopicUrl(42), $oldHash, $oldTorrent);

    strictAssertSame(null, $result, 'Successful replacement propagates createTorrent result');
    strictAssertSame(1, count(ruTrackerChecker::$created), 'Changed guest torrent is replaced once');
    $patched = @new Torrent(ruTrackerChecker::$created[0]['payload']);
    strictAssertTrue(!$patched->errors(), 'Patched replacement torrent must remain valid');
    strictAssertSame($guestHash, $patched->hash_info(), 'Passkey patch must not change info hash');
    strictAssertTrue(
        strpos($patched->announce(), '/announce?uk=' . $realPasskey) !== false,
        'Primary announce gets the reusable profile passkey in query form'
    );
    $patchedRaw = (string) $patched;
    strictAssertTrue(
        strpos($patchedRaw, 'http://ipv6.bt.searchtor.to/announce?uk=' . $realPasskey) !== false,
        'Official alternate announce gets the reusable profile passkey'
    );
    strictAssertTrue(
        strpos($patchedRaw, 'http://bt.nnmclub.example/' . $dummyPasskey . '/announce') !== false,
        'Lookalike tracker URL remains unchanged'
    );
    strictAssertTrue(
        strpos($patchedRaw, 'bt.nnmclub.example/announce?uk=' . $realPasskey) === false,
        'Reusable profile passkey is never sent to a lookalike tracker host'
    );
    strictAssertTrue(strpos($patchedRaw, 'https://example.test/announce') !== false, 'Unrelated announce URL remains unchanged');
});

$suite->test('dynamic credential is never transplanted into a changed torrent', function () use ($realPasskey, $dummyPasskey) {
    nnmReset();
    $oldRaw = strictTorrentRaw(
        'old-dynamic.bin',
        'http://bt.searchtor.to/' . $realPasskey . '/announce',
        nnmTopicUrl(42)
    );
    $oldTorrent = @new Torrent($oldRaw);
    $oldHash = $oldTorrent->hash_info();
    $guestRaw = strictTorrentRaw(
        'new-dynamic.bin',
        'http://bt.searchtor.to/' . $dummyPasskey . '/announce',
        nnmTopicUrl(42)
    );
    Snoopy::queue(
        nnmDynamicScrapeUrl('bt.searchtor.to', $realPasskey, $oldHash),
        200,
        strictScrapePayload($oldHash, false)
    );
    Snoopy::queue(nnmTopicUrl(42), 200, '<a href="download.php?id=7">download</a>');
    Snoopy::queue(nnmDownloadUrl(7), 200, $guestRaw);

    $result = NNMClubCheckImpl::download_torrent(nnmTopicUrl(42), $oldHash, $oldTorrent);

    strictAssertSame(ruTrackerChecker::STE_ERROR, $result, 'A changed torrent needs a reusable profile credential');
    strictAssertSame(0, count(ruTrackerChecker::$created), 'Per-distribution credential must not be copied to a new hash');
});

$suite->test('array topic parameters are rejected without warnings', function () use ($dummyPasskey) {
    nnmReset();
    $url = 'https://nnmclub.to/forum/viewtopic.php?t[]=42';
    $raw = strictTorrentRaw(
        'malformed-topic.bin',
        'http://bt.searchtor.to/' . $dummyPasskey . '/announce',
        $url
    );
    $torrent = @new Torrent($raw);
    set_error_handler(function ($severity, $message, $file, $line) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });
    try {
        $result = NNMClubCheckImpl::download_torrent($url, $torrent->hash_info(), $torrent);
    } finally {
        restore_error_handler();
    }

    strictAssertSame(ruTrackerChecker::STE_NOT_NEED, $result, 'Non-scalar topic IDs are invalid');
    strictAssertSame(0, count(Snoopy::$requests), 'Invalid topic references must not trigger network requests');
});

$suite->test('Cloudflare challenge is a reachability error', function () use ($dummyPasskey) {
    nnmReset();
    $raw = strictTorrentRaw(
        'challenge.bin',
        'http://bt02.nnm-club.cc:2710/' . $dummyPasskey . '/announce',
        nnmTopicUrl(42)
    );
    $torrent = @new Torrent($raw);
    strictAssertTrue(!$torrent->errors(), 'Challenge torrent fixture must parse');
    Snoopy::queue(
        nnmTopicUrl(42),
        200,
        '<html><div id="cf-chl">Cloudflare Turnstile challenge</div></html>'
    );

    $result = NNMClubCheckImpl::download_torrent(nnmTopicUrl(42), $torrent->hash_info(), $torrent);

    strictAssertSame(
        ruTrackerChecker::STE_CANT_REACH_TRACKER,
        $result,
        'Challenge page is temporary tracker unavailability'
    );
    strictAssertSame(0, count(ruTrackerChecker::$created), 'Challenge page never replaces a torrent');
});

$suite->test('donor passkey is used in memory without rewriting session torrent', function () use ($realPasskey, $dummyPasskey) {
    nnmReset();
    $tempDir = sys_get_temp_dir() . '/nnmclub-donor-red-' . getmypid() . '-' . mt_rand();
    mkdir($tempDir, 0700, true);

    try {
        $targetRaw = strictTorrentRaw(
            'target.bin',
            'http://bt02.nnm-club.cc:2710/' . $dummyPasskey . '/announce',
            nnmTopicUrl(42),
            null,
            array(
                'libtorrent_resume' => array('bitfield' => 1),
                'rtorrent' => array('state' => 1),
            )
        );
        $target = @new Torrent($targetRaw);
        strictAssertTrue(!$target->errors(), 'Target torrent fixture must parse');
        $targetHash = $target->hash_info();
        $targetPath = $tempDir . '/' . $targetHash . '.torrent';
        file_put_contents($targetPath, $targetRaw);

        $donorRaw = strictTorrentRaw(
            'donor.bin',
            'http://bt.searchtor.to/announce?uk=' . $realPasskey,
            nnmTopicUrl(77)
        );
        file_put_contents($tempDir . '/' . str_repeat('D', 40) . '.torrent', $donorRaw);

        rTorrentSettings::get()->session = $tempDir . '/';
        Snoopy::queue(
            nnmStaticScrapeUrl('bt.searchtor.to', $realPasskey, $targetHash),
            200,
            strictScrapePayload($targetHash, true)
        );

        $before = file_get_contents($targetPath);
        $result = NNMClubCheckImpl::download_torrent(nnmTopicUrl(42), $targetHash, $target);
        $after = file_get_contents($targetPath);

        strictAssertSame(ruTrackerChecker::STE_UPTODATE, $result, 'Donor passkey can authenticate scrape');
        strictAssertSame(
            $before,
            $after,
            'Donor passkey lookup must not mutate the live rTorrent session file'
        );
    } finally {
        strictRemoveTree($tempDir);
    }
});

$suite->test('hostile deeply nested scrape payload is dismissed without recursion', function () {
    nnmReset();
    $hash = str_repeat('AB', 20);
    $binary = hex2bin($hash);
    // With the old recursive bencode decoder this payload exhausted the call
    // stack / memory limit and killed the whole test process.
    $hostile = 'd' . str_repeat('l', 300000);
    strictAssertSame(
        false,
        strictInvoke('NNMClubCheckImpl', 'scrapeContainsHash', array($hostile, $binary)),
        'a hostile payload must simply not match'
    );
    strictAssertSame(
        true,
        strictInvoke('NNMClubCheckImpl', 'scrapeContainsHash', array(strictScrapePayload($hash, true), $binary)),
        'a well-formed payload listing the hash must match'
    );
    strictAssertSame(
        false,
        strictInvoke('NNMClubCheckImpl', 'scrapeContainsHash', array(strictScrapePayload($hash, false), $binary)),
        'a well-formed payload without the hash must not match'
    );
});

$suite->test('guest transport failure with a curl exit code is logged', function () {
    nnmReset();
    // The https path stores curl's exit code (6 = DNS failure) as the status.
    Snoopy::queue('https://nnmclub.to/forum/viewtopic.php?t=1', 6, '');
    $client = new Snoopy();
    strictInvoke('NNMClubCheckImpl', 'guestFetch', array($client, 'https://nnmclub.to/forum/viewtopic.php?t=1'));
    $failureLogs = array_values(array_filter(ruTrackerChecker::$logs, function ($line) {
        return strpos($line, 'Guest fetch failed') !== false;
    }));
    strictAssertSame(1, count($failureLogs), 'a curl exit-code status must be logged as a failed guest fetch');
    strictAssertTrue(strpos($failureLogs[0], 'status=6') !== false, 'the log line must carry the status');
});

exit($suite->run());
