<?php

class RuTrackerCheckImpl
{
    static public function download_torrent($url, $hash, $old_torrent)
    {
        if (preg_match('`^https?://rutracker\.(org|cr|net|nl)/forum/viewtopic\.php\?t=(?P<id>\d+)$`', $url, $matches)) {
            $topic_id = $matches["id"];
            $req_url = "http://api.rutracker.org/v1/get_tor_hash?by=topic_id&val=" . $topic_id;
            $client = ruTrackerChecker::makeClient($req_url);
            if ($client->status != 200) return ruTrackerChecker::STE_CANT_REACH_TRACKER;
            $ret = json_decode($client->results, true);
            if (array_key_exists("result", $ret)) $ret = $ret["result"];
            if ($ret && array_key_exists($topic_id, $ret) && (strtoupper($ret[$topic_id]) == $hash)) {
                return ruTrackerChecker::STE_UPTODATE;
                }
            $client->setcookies();
            $client->fetchComplex("https://rutracker.org/forum/dl.php?t=" . $topic_id);
            if ($client->status != 200) return (($client->status < 0) ? ruTrackerChecker::STE_CANT_REACH_TRACKER : ruTrackerChecker::STE_DELETED);
            return ruTrackerChecker::createTorrent($client->results, $hash);
        }
        return ruTrackerChecker::STE_NOT_NEED;
    }
}

ruTrackerChecker::registerTracker("/rutracker\./", "/rutracker\.|t-ru\.org/", "RuTrackerCheckImpl::download_torrent");
