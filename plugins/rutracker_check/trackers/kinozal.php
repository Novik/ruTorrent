<?php

class KinozalCheckImpl
{
    // Kinozal serves no torrent data to guests: get_srv_details.php answers
    // with a plain "not authorized" line and dl.kinozal.guru redirects to
    // login.php. Both of those answers -- and the login page itself -- offer
    // registration; no authenticated answer does (measured 2026-08-07 against
    // the live site, authorized and anonymous, on both endpoints). So this one
    // link is what tells "we are locked out" apart from "the tracker replied".
    const GUEST_MARKER = '/signup.php';

    // The tracker's own verdict for an id it no longer serves: HTTP 200 with
    // this body, in an authenticated session. It is the only authoritative
    // deletion signal this handler has; everything it merely fails to check
    // stays retryable, so a login wall, a dead socket or a layout change can
    // never masquerade as a removed release.
    const MISSING_MARKER = 'Торрент файл не найден';

    // get_srv_details.php is served as UTF-8 while the rest of the site is
    // windows-1251, so a needle is looked up in both encodings rather than
    // pinned to whichever charset that endpoint happens to use today.
    static private function bodyHas($body, $needle)
    {
        if (!is_string($body) || $body === '') return false;
        if (strpos($body, $needle) !== false) return true;
        if (function_exists('iconv')) {
            $legacy = @iconv('UTF-8', 'CP1251//IGNORE', $needle);
            if (is_string($legacy) && $legacy !== '' && strpos($body, $legacy) !== false) return true;
        }
        return false;
    }

    static private function isGuestAnswer($body)
    {
        return self::bodyHas($body, self::GUEST_MARKER);
    }

    // createTorrent() treats an unparseable payload as proof that the topic is
    // gone (check.php's legacy contract), so nothing reaches it before it is
    // known to be metainfo -- the same order rutracker.php's metadata harvest
    // validates in.
    static private function isMetainfo($payload)
    {
        if (!is_string($payload) || $payload === '') return false;
        // PHP 7.4 warns when Torrent probes binary metainfo as a filename.
        $torrent = @new Torrent($payload);
        return !$torrent->errors() && strlen((string) $torrent->hash_info()) === 40;
    }

    // The state constant carries the whole user-facing verdict: init.js renders
    // it through theUILang.chkResults, which every lang/ file translates. The
    // detail below stays in the debug log, which is developer-facing and
    // gated by $rutrackerCheckDebug -- no untranslated text reaches the UI.
    static private function cantReach($log)
    {
        ruTrackerChecker::logDebug($log);
        return ruTrackerChecker::STE_CANT_REACH_TRACKER;
    }

    static public function download_torrent($url, $hash, $old_torrent)
    {
        if (!preg_match('`^https?://kinozal\.(tv|me|guru)/details\.php\?id=(?P<id>\d+)$`', $url, $matches))
            return ruTrackerChecker::STE_NOT_NEED;

        $id = $matches["id"];
        $client = ruTrackerChecker::makeClient("https://kinozal.guru/get_srv_details.php?action=2&id=".$id);
        if ($client->status != 200)
            return self::cantReach("get_srv_details failed: status=".$client->status." id=".$id);

        $details = (string) $client->results;
        if (self::isGuestAnswer($details))
            return self::cantReach("get_srv_details answered a guest page, check the loginmgr account: id=".$id);
        if (self::bodyHas($details, self::MISSING_MARKER))
            return ruTrackerChecker::STE_DELETED;

        // Strict comparison: loose == reads a hex hash shaped like scientific
        // notation as a number -- '1E' followed by 38 zeros == '00...01'
        // (both are numerically 1) -- so two different 40-char hashes could
        // pass as equal.
        if (preg_match('`<li>.*(?P<hash>[0-9A-Fa-f]{40})</li>`', $details, $matches1)
            && strtoupper($matches1["hash"]) === strtoupper((string) $hash))
            return ruTrackerChecker::STE_UPTODATE;

        $client->setcookies();
        $client->fetchComplex("https://dl.kinozal.guru/download.php?id=".$id);
        // A guest download is a redirect to login.php. Whether Snoopy follows
        // it to the 200 login page (the guest marker below catches that) or
        // stops on a 3xx, the answer is treated as a login wall either way:
        // a non-200 status is "could not fetch", never "deleted".
        if ($client->status != 200)
            return self::cantReach("download.php failed: status=".$client->status." id=".$id);

        // Metainfo first: bytes that parse ARE the torrent, whatever text they
        // happen to contain, so a torrent is never mistaken for a login wall.
        $payload = (string) $client->results;
        if (self::isMetainfo($payload))
            return ruTrackerChecker::createTorrent($payload, $hash);
        if (self::isGuestAnswer($payload))
            return self::cantReach("download.php answered a guest page, check the loginmgr account: id=".$id);
        return self::cantReach("download.php returned no metainfo: id=".$id." bytes=".strlen($payload));
    }
}

ruTrackerChecker::registerTracker("/kinozal\./", "/kinozal\.tv|torrent4me\.com|tor4me\.info|tor2me\.info/", "KinozalCheckImpl::download_torrent");
