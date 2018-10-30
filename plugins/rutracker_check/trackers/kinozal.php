<?php

class KinozalCheckImpl
{
    static public function download_torrent($url, $hash, $old_torrent)
    {
        if (preg_match('`^https?://kinozal\.(tv|me|guru)/details\.php\?id=(?P<id>\d+)$`', $url, $matches)) {
            $client = ruTrackerChecker::makeClient("https://kinozal.guru/get_srv_details.php?action=2&id=".$matches["id"]);
            if ($client->status != 200) return ruTrackerChecker::STE_CANT_REACH_TRACKER;
            if (preg_match('`<li>.*(?P<hash>[0-9A-Fa-f]{40})</li>`', $client->results, $matches1)) {
                if (strtoupper($matches1["hash"])==$hash) {
                    return  ruTrackerChecker::STE_UPTODATE;
                }
            }
            $client->setcookies();
            $client->fetchComplex("https://dl.kinozal.guru/download.php?id=".$matches["id"]);
            if ($client->status != 200) return (($client->status < 0) ? ruTrackerChecker::STE_CANT_REACH_TRACKER : ruTrackerChecker::STE_DELETED);
            return ruTrackerChecker::createTorrent($client->results, $hash);
        }
        return ruTrackerChecker::STE_NOT_NEED;
    }
}

ruTrackerChecker::registerTracker("/kinozal\./", "/kinozal\.tv|torrent4me\.com|tor4me\.info|tor2me\.info/", "KinozalCheckImpl::download_torrent");
