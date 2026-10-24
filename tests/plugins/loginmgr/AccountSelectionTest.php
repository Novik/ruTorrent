<?php

/**
 * accountManager picks an account for a URL by asking each enabled account
 * test($url). Getting that wrong is not a cosmetic bug: the account it picks
 * is the one whose cookies Snoopy then sends to whatever host the URL really
 * names, and the URL does not have to come from the user -- plugins/rss
 * follows links out of a feed, and plugins/extsearch out of a search result.
 */

require_once(__DIR__ . '/../../../plugins/loginmgr/accounts.php');
foreach (glob(__DIR__ . '/../../../plugins/loginmgr/accounts/*.php') as $accountFile) {
    require_once($accountFile);
}

// Five accounts name no site until one is configured; YggTorrentOriginTest and
// ConfiguredOriginTest are about that. Here they have one, so that they are
// asked like every other account.
$yggTorrentOrigin = 'https://yggtorrent.example';
$abTorrentsOrigin = 'https://abtorrents.example';
$revolutionTTOrigin = 'https://revolutiontt.example';
$tfileOrigin = 'https://tfile.example';
$redactedOrigin = 'https://redacted.example';

// A site still configured http, to pin the direction the rule allows.
class ProbeHttpSiteAccount extends commonAccount
{
    public $url = 'http://http-only.example';
    protected function isOK($client) { return true; }
    protected function login($c, $l, $p, &$u, &$m, &$ct, &$b, &$f) { return false; }
}

function selAssertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '; expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true)
        );
    }
}

$tests = array(
    'an account is chosen by the url host, not by a substring of the url' => function () {
        // A prefix match accepts https://tracker.example@evil.test/ (the name
        // is userinfo) and https://tracker.example.evil.test/ (the name is one
        // label of a longer domain); matching the name anywhere accepts
        // https://evil.test/path/tracker.example/x. All three would have sent
        // the account's cookies to a host the attacker controls, and the url
        // need not come from the user -- plugins/rss follows feed links.
        $cases = array(
            array('ABTorrentsAccount', 'https://abtorrents.example/x', true),
            array('ABTorrentsAccount', 'https://abtorrents.example@evil.test/', false),
            array('ABTorrentsAccount', 'https://abtorrents.example.evil.test/', false),
            array('ABTorrentsAccount', 'https://evil.test/abtorrents.example/', false),
            array('TfileAccount', 'https://tfile.example/forum/index.php', true),
            array('TfileAccount', 'https://tfile.example/other/', false),
            array('TfileAccount', 'https://evil.test/x/tfile.example/forum/', false),
            array('RedactedAccount', 'https://redacted.example/torrents.php?id=1', true),
            array('RedactedAccount', 'https://redacted.example.evil.test/torrents.php?id=1', false),
            array('RevolutionTTAccount', 'https://revolutiontt.example/download.php/1/x', true),
            array('RevolutionTTAccount', 'https://revolutiontt.example@evil.test/download.php/1/x', false),
            array('KinozalTVAccount', 'https://kinozal.guru/details.php?id=1', true),
            array('KinozalTVAccount', 'https://dl.kinozal.guru/download.php?id=1', true),
            array('KinozalTVAccount', 'https://evil.test/path/kinozal.guru/feed', false),
            array('ruTrackerAccount', 'https://rutracker.org/forum/dl.php?t=1', true),
            array('ruTrackerAccount', 'https://rutracker.org/other/', false),
            array('ruTrackerAccount', 'https://evil.test/x/rutracker.org/forum/', false),
            array('TapochekNetAccount', 'https://tapochek.net/x', true),
            array('TapochekNetAccount', 'https://tapochek.net.evil.test/x', false),
            array('YggTorrentAccount', 'https://yggtorrent.example/engine/download_torrent?id=1', true),
            array('YggTorrentAccount', 'https://ygg-lookalike.example/engine/download_torrent?id=1', false),
            array('YggTorrentAccount', 'https://evil.test/x/yggtorrent.example/engine/download_torrent?id=1', false),
            array('LostFilmAccount', 'https://lostfilm.tv/download.php?id=7&', true),
            array('LostFilmAccount', 'https://lostfilm.tv.evil.test/download.php?id=7&', false),
        );
        foreach ($cases as $case) {
            list($class, $url, $expected) = $case;
            $account = new $class();
            selAssertSame($expected, (bool) $account->test($url), $class . ' vs ' . $url);
        }
    },

    'a url may not weaken the scheme its site is configured with' => function () {
        // Matched over http, an https site would have this account's cookies
        // put on the wire in clear by the very first request, before any
        // redirect could upgrade it -- and the url can arrive from a feed.
        foreach (array(
            array('LostFilmAccount', 'http://lostfilm.tv/download.php?id=7&'),
            array('TfileAccount', 'http://tfile.example/forum/index.php'),
            array('AniDUBAccount', 'http://tr.anidub.com/'),
            array('ABTorrentsAccount', 'http://abtorrents.example/x'),
        ) as $case) {
            list($class, $url) = $case;
            $account = new $class();
            selAssertSame(false, (bool) $account->test($url),
                $class . ' must not claim the http form of an https site');
        }
    },

    'an https url still reaches a site whose own scheme is http' => function () {
        // The other direction costs nothing to allow: an https url to a site
        // that really is http-only simply fails to connect.
        $account = new ProbeHttpSiteAccount();
        selAssertSame(true, (bool) $account->test('https://http-only.example/x'), 'https is accepted');
        selAssertSame(true, (bool) $account->test('http://http-only.example/x'), 'so is its own scheme');
    },

    'no account claims a url whose host it does not own' => function () {
        // A net rather than a proof: each account is asked about urls that
        // carry its own name but are served by someone else. It cannot know
        // every path a given tracker requires, so a rejection here may be for
        // the path rather than the host -- but an acceptance is always wrong,
        // and this is what catches the next test() written against the raw
        // url string instead of the parsed host.
        $asked = 0;
        foreach (get_declared_classes() as $class) {
            if (!is_subclass_of($class, 'commonAccount') || $class === 'ProbeHttpSiteAccount') {
                continue;
            }
            $account = new $class();
            $host = parse_url((string) $account->url, PHP_URL_HOST);
            if (!$host) {
                continue;
            }
            $asked++;
            $elsewhere = array(
                'https://evil.test/x/' . $host . '/forum/dl.php?t=1',
                'https://evil.test/x/' . $host . '/download.php?id=1',
                'https://evil.test/x/' . $host . '/engine/download_torrent?id=1',
                'https://evil.test/x/' . $host . '/',
                'https://' . $host . '@evil.test/forum/dl.php?t=1',
                'https://' . $host . '@evil.test/',
                'https://' . $host . '.evil.test/forum/dl.php?t=1',
            );
            foreach ($elsewhere as $url) {
                selAssertSame(false, (bool) $account->test($url),
                    $class . ' must not claim ' . var_export($url, true));
            }
        }
        // The accounts are loaded by the glob at the top of this file, and the
        // cases that name a class by hand are the only ones that notice when
        // it matches nothing. This one would simply ask no account anything.
        selAssertSame(true, $asked > 0, 'accounts were found to ask');
    },

    'a url with no host at all matches nothing' => function () {
        $asked = 0;
        foreach (get_declared_classes() as $class) {
            if (!is_subclass_of($class, 'commonAccount') || $class === 'ProbeHttpSiteAccount') {
                continue;
            }
            $asked++;
            $account = new $class();
            foreach (array('', 'not a url', '/relative/path', 'javascript:alert(1)') as $url) {
                selAssertSame(false, (bool) $account->test($url),
                    $class . ' must not claim ' . var_export($url, true));
            }
        }
        selAssertSame(true, $asked > 0, 'accounts were found to ask');
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
