<?php

class TfileCheckImpl
{
    static public function download_torrent($url, $hash, $old_torrent)
    {
        if (preg_match('`^https?://tfile\.me/forum/viewtopic\.php\?p=(?P<id>\d+)$`', $url, $matches)) {
            $client = ruTrackerChecker::makeClient("http://megatfile.cc/forum/viewtopic.php?p=".$matches["id"]);
            if ($client->status != 200) return ruTrackerChecker::STE_CANT_REACH_TRACKER;
            if (preg_match('`Info hash:</td><td><strong>(?P<hash>[0-9A-Fa-f]{40})</strong></td>`', $client->results, $matches)) {
                if (strtoupper($matches["hash"])==$hash) {
                    return  ruTrackerChecker::STE_UPTODATE;
                }
                if (preg_match('`\"download.php\?id=(?P<id>\d+)`', $client->results, $matches)) {
                    $client->setcookies();
                    $client->fetchComplex("http://megatfile.cc/forum/download.php?id=".$matches["id"]);
                    if ($client->status != 200) return (($client->status < 0) ? ruTrackerChecker::STE_CANT_REACH_TRACKER : ruTrackerChecker::STE_DELETED);
                    return ruTrackerChecker::createTorrent($client->results, $hash);
                }
            }
        }
        return ruTrackerChecker::STE_NOT_NEED;
    }
}

ruTrackerChecker::registerTracker("/tfile\.me/", "/tfile\.|peersteers\.org/", "TfileCheckImpl::download_torrent");
