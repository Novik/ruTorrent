<?php

class NNMClubCheckImpl
{
    static public function download_torrent($url, $hash, $old_torrent)
    {
        if (preg_match('`^https?://(nnm-club|nnmclub)\.(ru|me|to|name|tv)/forum/viewtopic\.php\?p=(?P<id>\d+)$`', $url, $matches)) {
            $client = ruTrackerChecker::makeClient("https://nnmclub.to/forum/viewtopic.php?p=".$matches["id"]);
            if ($client->status != 200) return ruTrackerChecker::STE_CANT_REACH_TRACKER;
            if (preg_match('`btih:(?P<hash>[0-9A-Fa-f]{40})&tr`', $client->results, $matches)) {
                if (strtoupper($matches["hash"])==$hash) {
                    return  ruTrackerChecker::STE_UPTODATE;
                }
                if (preg_match('`\"download.php\?id=(?P<id>\d+)\"`', $client->results, $matches)) {
                    $client->setcookies();
                    $client->fetchComplex("https://nnmclub.to/forum/download.php?id=".$matches["id"]);
                    if ($client->status != 200) return (($client->status < 0) ? ruTrackerChecker::STE_CANT_REACH_TRACKER : ruTrackerChecker::STE_DELETED);
                    return ruTrackerChecker::createTorrent($client->results, $hash);
                }
            }
        }
        return ruTrackerChecker::STE_NOT_NEED;
    }
}

ruTrackerChecker::registerTracker("/(nnm-club|nnmclub)\./", "/nnm-club\./", "NNMClubCheckImpl::download_torrent");
