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
        strpos($patched->announce(), 'http://bt.searchtor.to/' . $realPasskey . '/announce') !== false,
        'Primary announce keeps the path form the tracker served and carries the account passkey'
    );
    $patchedRaw = (string) $patched;
    strictAssertTrue(
        strpos($patchedRaw, 'http://ipv6.bt.searchtor.to/' . $realPasskey . '/announce') !== false,
        'Official alternate announce gets the account passkey in the same form'
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

// NNMClub serves one account passkey per user and writes it into the announce
// URL of every torrent that account downloads: `/PASSKEY/announce` in
// currently served .torrents, `announce?uk=PASSKEY` in older downloads. A
// torrent's own passkey is therefore the right key for its own replacement,
// in whichever form the tracker served the replacement's URLs.
$suite->test('a torrent path-form passkey is reused for its own replacement', function () use ($realPasskey, $dummyPasskey) {
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
        nnmTopicUrl(42),
        array(
            array('http://bt02.nnm-club.cc:2710/' . $dummyPasskey . '/announce'),
            array('http://bt.nnmclub.example/' . $dummyPasskey . '/announce'),
        )
    );
    Snoopy::queue(
        nnmDynamicScrapeUrl('bt.searchtor.to', $realPasskey, $oldHash),
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
    $patchedRaw = (string) $patched;
    strictAssertTrue(
        strpos($patchedRaw, 'http://bt.searchtor.to/' . $realPasskey . '/announce') !== false,
        'The path form is preserved and carries the account passkey'
    );
    strictAssertTrue(
        strpos($patchedRaw, 'http://bt02.nnm-club.cc:2710/' . $realPasskey . '/announce') !== false,
        'Every official host of the replacement gets the same account passkey'
    );
    strictAssertTrue(
        strpos($patchedRaw, 'http://bt.nnmclub.example/' . $dummyPasskey . '/announce') !== false,
        'A lookalike tracker host keeps the dummy passkey'
    );
    strictAssertTrue(
        strpos($patchedRaw, 'bt.nnmclub.example/' . $realPasskey) === false,
        'The account passkey is never written to a lookalike tracker host'
    );
});

$suite->test('the query form is preserved when the tracker still serves it', function () use ($realPasskey, $dummyPasskey) {
    nnmReset();
    $oldRaw = strictTorrentRaw(
        'old-mixed.bin',
        'http://bt02.nnm-club.cc:2710/' . $realPasskey . '/announce',
        nnmTopicUrl(43)
    );
    $oldTorrent = @new Torrent($oldRaw);
    strictAssertTrue(!$oldTorrent->errors(), 'Old torrent fixture must parse');
    $oldHash = $oldTorrent->hash_info();
    $guestRaw = strictTorrentRaw(
        'new-mixed.bin',
        'http://bt.nnm-club.ru:2710/announce?uk=' . $dummyPasskey,
        nnmTopicUrl(43)
    );
    // The passkey is the account's key on every host, so a failed scrape on
    // the torrent's own host may consult the current official endpoint in the
    // same path form before falling through to the guest download.
    Snoopy::queue(
        nnmDynamicScrapeUrl('bt02.nnm-club.cc:2710', $realPasskey, $oldHash),
        200,
        strictScrapePayload($oldHash, false)
    );
    Snoopy::queue(
        nnmDynamicScrapeUrl('bt.searchtor.to', $realPasskey, $oldHash),
        200,
        strictScrapePayload($oldHash, false)
    );
    Snoopy::queue(nnmTopicUrl(43), 200, '<a href="download.php?id=8">download</a>');
    Snoopy::queue(nnmDownloadUrl(8), 200, $guestRaw);

    $result = NNMClubCheckImpl::download_torrent(nnmTopicUrl(43), $oldHash, $oldTorrent);

    strictAssertSame(null, $result, 'Successful replacement propagates createTorrent result');
    strictAssertSame(
        array(
            array('fetchComplex', nnmDynamicScrapeUrl('bt02.nnm-club.cc:2710', $realPasskey, $oldHash)),
            array('fetchComplex', nnmDynamicScrapeUrl('bt.searchtor.to', $realPasskey, $oldHash)),
            array('fetch', nnmTopicUrl(43)),
            array('fetch', nnmDownloadUrl(8)),
        ),
        Snoopy::$requests,
        'A failed scrape on the torrent\'s own host consults the official fallback first'
    );
    strictAssertSame(1, count(ruTrackerChecker::$created), 'A legacy-form replacement is still replaced');
    $patchedRaw = (string) @new Torrent(ruTrackerChecker::$created[0]['payload']);
    strictAssertTrue(
        strpos($patchedRaw, 'announce?uk=' . $realPasskey) !== false,
        'A query-form announce keeps that form and carries the account passkey'
    );
    strictAssertTrue(
        strpos($patchedRaw, $dummyPasskey) === false,
        'The dummy passkey is gone from the replacement'
    );
});

$suite->test('a replacement already carrying the account passkey is accepted', function () use ($realPasskey) {
    nnmReset();
    $oldRaw = strictTorrentRaw(
        'old-samekey.bin',
        'http://bt.searchtor.to/' . $realPasskey . '/announce',
        nnmTopicUrl(46)
    );
    $oldTorrent = @new Torrent($oldRaw);
    strictAssertTrue(!$oldTorrent->errors(), 'Old torrent fixture must parse');
    $oldHash = $oldTorrent->hash_info();
    // download.php can serve URLs that already hold the account passkey in
    // canonical form; "nothing to change" must not read as "no URLs found".
    $guestRaw = strictTorrentRaw(
        'new-samekey.bin',
        'http://bt.searchtor.to/' . $realPasskey . '/announce',
        nnmTopicUrl(46)
    );
    $guestTorrent = @new Torrent($guestRaw);
    strictAssertTrue(!$guestTorrent->errors(), 'Guest torrent fixture must parse');
    $guestHash = $guestTorrent->hash_info();
    strictAssertTrue($guestHash !== $oldHash, 'Guest fixture must represent an update');

    Snoopy::queue(
        nnmDynamicScrapeUrl('bt.searchtor.to', $realPasskey, $oldHash),
        200,
        strictScrapePayload($oldHash, false)
    );
    Snoopy::queue(nnmTopicUrl(46), 200, '<a href="download.php?id=11">download</a>');
    Snoopy::queue(nnmDownloadUrl(11), 200, $guestRaw);

    $result = NNMClubCheckImpl::download_torrent(nnmTopicUrl(46), $oldHash, $oldTorrent);

    strictAssertSame(null, $result, 'An already-authenticated replacement is loaded, not refused');
    strictAssertSame(1, count(ruTrackerChecker::$created), 'The replacement is loaded exactly once');
    $patched = @new Torrent(ruTrackerChecker::$created[0]['payload']);
    strictAssertTrue(!$patched->errors(), 'Loaded replacement torrent must remain valid');
    strictAssertSame($guestHash, $patched->hash_info(), 'An unchanged payload keeps its info hash');
    strictAssertSame(
        'http://bt.searchtor.to/' . $realPasskey . '/announce',
        $patched->announce(),
        'The already-correct announce URL is untouched'
    );
});

$suite->test('a changed torrent without any passkey anywhere is refused', function () use ($dummyPasskey) {
    nnmReset();
    $oldRaw = strictTorrentRaw(
        'old-nokey.bin',
        'http://bt.searchtor.to/' . $dummyPasskey . '/announce',
        nnmTopicUrl(44)
    );
    $oldTorrent = @new Torrent($oldRaw);
    $oldHash = $oldTorrent->hash_info();
    $guestRaw = strictTorrentRaw(
        'new-nokey.bin',
        'http://bt.searchtor.to/' . $dummyPasskey . '/announce',
        nnmTopicUrl(44)
    );
    Snoopy::queue(nnmTopicUrl(44), 200, '<a href="download.php?id=9">download</a>');
    Snoopy::queue(nnmDownloadUrl(9), 200, $guestRaw);

    $result = NNMClubCheckImpl::download_torrent(nnmTopicUrl(44), $oldHash, $oldTorrent);

    strictAssertSame(ruTrackerChecker::STE_ERROR, $result, 'Without a passkey the replacement is refused');
    strictAssertSame(0, count(ruTrackerChecker::$created), 'An unauthenticated replacement is never loaded');
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

// The donor is the one remaining cross-torrent transplant: consulted only
// when the torrent being replaced carries no usable key of its own, and only
// for keys another torrent published in the profile-wide `uk=` form. A
// path-form key in a foreign torrent may belong to whoever downloaded that
// file (real sessions do carry torrents fetched from other accounts).
$suite->test('a donor query-form passkey patches a keyless replacement', function () use ($realPasskey, $dummyPasskey) {
    nnmReset();
    $tempDir = sys_get_temp_dir() . '/nnmclub-donor-patch-' . getmypid() . '-' . mt_rand();
    mkdir($tempDir, 0700, true);

    try {
        $oldRaw = strictTorrentRaw(
            'old-donorpatch.bin',
            'http://bt02.nnm-club.cc:2710/' . $dummyPasskey . '/announce',
            nnmTopicUrl(45)
        );
        $oldTorrent = @new Torrent($oldRaw);
        strictAssertTrue(!$oldTorrent->errors(), 'Old torrent fixture must parse');
        $oldHash = $oldTorrent->hash_info();

        $donorRaw = strictTorrentRaw(
            'donor.bin',
            'http://bt.searchtor.to/announce?uk=' . $realPasskey,
            nnmTopicUrl(77)
        );
        file_put_contents($tempDir . '/' . str_repeat('E', 40) . '.torrent', $donorRaw);
        rTorrentSettings::get()->session = $tempDir . '/';

        $guestRaw = strictTorrentRaw(
            'new-donorpatch.bin',
            'http://bt.searchtor.to/' . $dummyPasskey . '/announce',
            nnmTopicUrl(45)
        );

        Snoopy::queue(
            nnmStaticScrapeUrl('bt.searchtor.to', $realPasskey, $oldHash),
            200,
            strictScrapePayload($oldHash, false)
        );
        Snoopy::queue(nnmTopicUrl(45), 200, '<a href="download.php?id=10">download</a>');
        Snoopy::queue(nnmDownloadUrl(10), 200, $guestRaw);

        $result = NNMClubCheckImpl::download_torrent(nnmTopicUrl(45), $oldHash, $oldTorrent);

        strictAssertSame(null, $result, 'A donor-patched replacement propagates createTorrent result');
        strictAssertSame(1, count(ruTrackerChecker::$created), 'The keyless replacement is patched and loaded');
        $patched = @new Torrent(ruTrackerChecker::$created[0]['payload']);
        strictAssertTrue(!$patched->errors(), 'Patched replacement torrent must remain valid');
        strictAssertTrue(
            strpos($patched->announce(), 'http://bt.searchtor.to/' . $realPasskey . '/announce') !== false,
            'The donor passkey is written in the form the replacement URL already uses'
        );
    } finally {
        strictRemoveTree($tempDir);
    }
});

$suite->test('a session path-form passkey is never donated to another torrent', function () use ($realPasskey, $dummyPasskey) {
    nnmReset();
    $tempDir = sys_get_temp_dir() . '/nnmclub-donor-path-' . getmypid() . '-' . mt_rand();
    mkdir($tempDir, 0700, true);

    try {
        $oldRaw = strictTorrentRaw(
            'old-nodonor.bin',
            'http://bt02.nnm-club.cc:2710/' . $dummyPasskey . '/announce',
            nnmTopicUrl(47)
        );
        $oldTorrent = @new Torrent($oldRaw);
        strictAssertTrue(!$oldTorrent->errors(), 'Old torrent fixture must parse');
        $oldHash = $oldTorrent->hash_info();

        $pathDonorRaw = strictTorrentRaw(
            'pathdonor.bin',
            'http://bt.searchtor.to/' . $realPasskey . '/announce',
            nnmTopicUrl(88)
        );
        file_put_contents($tempDir . '/' . str_repeat('F', 40) . '.torrent', $pathDonorRaw);
        rTorrentSettings::get()->session = $tempDir . '/';

        $guestRaw = strictTorrentRaw(
            'new-nodonor.bin',
            'http://bt.searchtor.to/' . $dummyPasskey . '/announce',
            nnmTopicUrl(47)
        );

        Snoopy::queue(nnmTopicUrl(47), 200, '<a href="download.php?id=12">download</a>');
        Snoopy::queue(nnmDownloadUrl(12), 200, $guestRaw);

        $result = NNMClubCheckImpl::download_torrent(nnmTopicUrl(47), $oldHash, $oldTorrent);

        strictAssertSame(ruTrackerChecker::STE_ERROR, $result, 'A foreign path-form key must not authenticate a replacement');
        strictAssertSame(0, count(ruTrackerChecker::$created), 'Nothing is loaded with a foreign path-form key');
        strictAssertSame(
            array(
                array('fetch', nnmTopicUrl(47)),
                array('fetch', nnmDownloadUrl(12)),
            ),
            Snoopy::$requests,
            'A path-form session key yields no credential, so no scrape is attempted'
        );
    } finally {
        strictRemoveTree($tempDir);
    }
});

$suite->test('injectAuthIntoUrl updates every credential form and defaults to the query form', function () use ($realPasskey, $dummyPasskey) {
    nnmReset();
    $cases = array(
        'path and query forms present: both are updated' => array(
            'http://bt.searchtor.to/' . $dummyPasskey . '/announce?uk=' . $dummyPasskey,
            'http://bt.searchtor.to/' . $realPasskey . '/announce?uk=' . $realPasskey,
        ),
        'keyless URL gets the query form every host generation accepts' => array(
            'http://bt.nnm-club.ru:2710/announce',
            'http://bt.nnm-club.ru:2710/announce?uk=' . $realPasskey,
        ),
        'an unrecognized path segment is kept, never doubled' => array(
            'http://bt.searchtor.to/' . str_repeat('a', 40) . '/announce',
            'http://bt.searchtor.to/' . str_repeat('a', 40) . '/announce?uk=' . $realPasskey,
        ),
    );
    foreach ($cases as $label => $case) {
        strictAssertSame(
            $case[1],
            strictInvoke('NNMClubCheckImpl', 'injectAuthIntoUrl', array($case[0], $realPasskey)),
            $label
        );
    }
    strictAssertSame(
        null,
        strictInvoke('NNMClubCheckImpl', 'injectAuthIntoUrl', array('http://bt.nnmclub.example/' . $dummyPasskey . '/announce', $realPasskey)),
        'a lookalike host is not a patchable NNMClub announce URL'
    );
    strictAssertSame(
        null,
        strictInvoke('NNMClubCheckImpl', 'injectAuthIntoUrl', array('https://nnmclub.to/forum/viewtopic.php?t=42', $realPasskey)),
        'a non-announce URL is not patchable'
    );
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
