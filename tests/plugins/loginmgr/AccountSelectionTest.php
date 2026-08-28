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
            array('ABTorrentsAccount', 'https://abtorrents.me/x', true),
            array('ABTorrentsAccount', 'https://abtorrents.me@evil.test/', false),
            array('ABTorrentsAccount', 'https://abtorrents.me.evil.test/', false),
            array('ABTorrentsAccount', 'https://evil.test/abtorrents.me/', false),
            array('KinozalTVAccount', 'https://kinozal.guru/details.php?id=1', true),
            array('KinozalTVAccount', 'https://dl.kinozal.guru/download.php?id=1', true),
            array('KinozalTVAccount', 'https://evil.test/path/kinozal.guru/feed', false),
            array('ruTrackerAccount', 'https://rutracker.org/forum/dl.php?t=1', true),
            array('ruTrackerAccount', 'https://rutracker.org/other/', false),
            array('ruTrackerAccount', 'https://evil.test/x/rutracker.org/forum/', false),
            array('TapochekNetAccount', 'https://tapochek.net/x', true),
            array('TapochekNetAccount', 'https://tapochek.net.evil.test/x', false),
            array('YggTorrentAccount', 'https://www.ygg.re/engine/download_torrent?id=1', true),
            array('YggTorrentAccount', 'https://evil.test/x/ygg.re/engine/download_torrent?id=1', false),
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
            array('TfileAccount', 'http://megatfile.cc/forum/index.php'),
            array('AniDUBAccount', 'http://tr.anidub.com/'),
            array('ABTorrentsAccount', 'http://abtorrents.me/x'),
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
        foreach (get_declared_classes() as $class) {
            if (!is_subclass_of($class, 'commonAccount') || $class === 'ProbeHttpSiteAccount') {
                continue;
            }
            $account = new $class();
            $host = parse_url((string) $account->url, PHP_URL_HOST);
            if (!$host) {
                continue;
            }
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
    },

    'a url with no host at all matches nothing' => function () {
        foreach (get_declared_classes() as $class) {
            if (!is_subclass_of($class, 'commonAccount') || $class === 'ProbeHttpSiteAccount') {
                continue;
            }
            $account = new $class();
            foreach (array('', 'not a url', '/relative/path', 'javascript:alert(1)') as $url) {
                selAssertSame(false, (bool) $account->test($url),
                    $class . ' must not claim ' . var_export($url, true));
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
exit($failures === 0 ? 0 : 1);
