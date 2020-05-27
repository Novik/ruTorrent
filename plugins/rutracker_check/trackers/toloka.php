<?php

// Toloka.to support by ReMMeR github@r3mm3r.net

class tolokaCheckImpl
{
    static public function download_torrent($url, $hash, $old_torrent)
    {
        if (preg_match('`^https?://toloka.to/p(?P<id>\d+)$`', $url, $matches)) {
            $topic_id = $matches["id"];
	    $req_url = "https://toloka.to/p".$topic_id;
	    sleep(5); // Do not want to be banned by cloudflare
            $client = ruTrackerChecker::makeClient($req_url);
            if ($client->status != 200) return ruTrackerChecker::STE_CANT_REACH_TRACKER;

	    $hash_now='';
	    if (preg_match('`href=\"magnet:[^:]+:[^:]+:(?P<hash>[0-9A-Fa-f]{40})`', $client->results, $matches)) {
		$hash_now = $matches["hash"];
	    }

	    $dow_id=0;
	    if (preg_match('`href=\"download.php\?id=(?P<id>\d+)`', $client->results, $matches)) {
		$dow_id = intval($matches["id"]);
	    }

            if ( $dow_id && strtoupper($hash_now) == $hash) {
                return ruTrackerChecker::STE_UPTODATE;
            }
            $client->setcookies();

	    sleep(5); // Do not want to be banned by cloudflare
            $client->fetchComplex("https://toloka.to/download.php?id=" . $dow_id);
            if ($client->status != 200) return (($client->status < 0) ? ruTrackerChecker::STE_CANT_REACH_TRACKER : ruTrackerChecker::STE_DELETED);
            return ruTrackerChecker::createTorrent($client->results, $hash);
        }
        return ruTrackerChecker::STE_NOT_NEED;
    }
}

ruTrackerChecker::registerTracker("/toloka\./", "/toloka.to/", "tolokaCheckImpl::download_torrent");
