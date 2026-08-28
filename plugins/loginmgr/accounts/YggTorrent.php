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

    // The tracker changes domain often, so the host is matched by shape rather
    // than by name -- but against the host, not against the whole URL. The
    // pattern this replaced also accepted
    // https://evil.test/x/ygg.example/engine/download_torrent?id=1, whose host
    // is the attacker's and which would have been sent this account's cookies.
    public function test($url)
    {
        $parts = @parse_url((string) $url);
        if(!is_array($parts) || empty($parts["host"]) ||
            (strtolower(isset($parts["scheme"]) ? $parts["scheme"] : '')!=='https'))
            return(false);
        if(!preg_match('/(^|\.)ygg[^.]*\.[^.]+$/i',$parts["host"]))
            return(false);
        if(strcasecmp(isset($parts["path"]) ? $parts["path"] : '','/engine/download_torrent')!==0)
            return(false);
        return(preg_match('/(^|&)id=/',isset($parts["query"]) ? $parts["query"] : '')===1);
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
