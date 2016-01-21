<?php


class AniDUBCheckImpl{
    static public function download_torrent($url, $hash, $old_torrent){
        if( preg_match( '`^http://tr\.anidub\.com/\?newsid=(?P<id>\d+)$`',$url, $matches ) ) {
            $topic_id = $matches["id"];
            $torrent_name = $old_torrent->name();
            $torrent_quality = null;
            $torrent_quality_type = "";

            # checking torrent quality from torrent file name
            if (preg_match('/\_\[((?<vq>\d{1,3})p)|(?<vq1>PSP)|(?<vq2>HWP)|(bdrip(?<vq3>\d{1,3})p)\]\_/i',
                $torrent_name, $qmatches)){

                if (array_key_exists("vq", $qmatches)){
                    $torrent_quality = $qmatches["vq"];  # tv quality
                    $torrent_quality_type = "tv";
                }
                if (array_key_exists("vq3", $qmatches)) {
                    $torrent_quality = strtolower($qmatches["vq3"]); # bd quality
                    $torrent_quality_type = "bd";
                }
                if (array_key_exists("vq1", $qmatches)) $torrent_quality = strtolower($qmatches["vq1"]);
                if (array_key_exists("vq2", $qmatches)) $torrent_quality = strtolower($qmatches["vq2"]);
            }

            // ToDo: if torrent doesn't contain any quality tag, try to guess in other ways

            # exit, no way to get torrent file without this
            if ($torrent_quality === null) return ruTrackerChecker::STE_NOT_NEED;

            $client = ruTrackerChecker::makeClient($url);
            if($client->status!=200) return ruTrackerChecker::STE_CANT_REACH_TRACKER;

            $resp = preg_replace( "/\r|\n/", "", $client->results);
            $q =$torrent_quality_type.$torrent_quality;  // e.g. tv.720
            $pattern = sprintf('/\<div\sid\=\"%s\"\>\<div\sid=\'[\d\w\_]+\'\>\s+\<div\sclass=\"torrent\_h\"\>\s+\<a\shref=\"(?P<url>[\w\d\_\/\.\=\?]+)\"\s/i', $q);
            if (!preg_match($pattern, $resp, $url_matches)) ruTrackerChecker::STE_CANT_REACH_TRACKER;

            if (!preg_match('/\/engine\/download\.php\?id=\d{1,10}/i', $url_matches["url"], $m)) ruTrackerChecker::STE_CANT_REACH_TRACKER;

            $client->setcookies();
            $client->fetchComplex("http://tr.anidub.com" . $url_matches["url"]);

            if(intval($client->status)!=200) return (($client->status<0) ? ruTrackerChecker::STE_CANT_REACH_TRACKER : ruTrackerChecker::STE_DELETED );

            return ruTrackerChecker::createTorrent($client->results, $hash);
        }

        return ruTrackerChecker::STE_NOT_NEED;
    }
}

ruTrackerChecker::registerTracker("/tr\.anidub\.com/", "/tr\.anidub\.com/", "AniDUBCheckImpl::download_torrent");
