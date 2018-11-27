<?php

class YggTorrentAccount extends commonAccount
{
    protected function isOK($client)
    {
        return (
            $client->status == 200
            && $client->results !== 'Vous devez vous connecter pour télécharger un torrent'
            && strpos($client->results, "S'identifier</a>") === false
        );
    }

    public function test($url)
    {
    	return( preg_match( '/https:\/\/(.*\.)?ygg.*\..*\/engine\/download_torrent\?id=/', $url ) === 1 );
    }

    protected function login($client, $login, $password, &$url, &$method, &$content_type, &$body, &$is_result_fetched)
    {
        $is_result_fetched = false;
        if ($client->fetch($url)) {
            $client->setcookies();
            $client->referer = $url;
            if( ($domain = parse_url($url, PHP_URL_HOST)) &&
                $client->fetch("https://".$domain."/user/login", "POST", "application/x-www-form-urlencoded",
		    "id=" . rawurlencode($login) . "&pass=" . rawurlencode($password) . '&submit=') )
	    {
                $client->setcookies();
                return(true);
            }
        }
        return (false);
    }
}
