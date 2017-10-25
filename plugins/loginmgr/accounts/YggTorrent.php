<?php

class YggTorrentAccount extends commonAccount
{
    public $url = "https://yggtorrent.com";

    protected function isOK($client)
    {
        return (
            strpos($client->results, 'Ces identifiants sont invalides') === false
            && strpos($client->results, 'Vous devez vous connecter pour télécharger un torrent') === false
            && strpos($client->results, '<li><a href="https://yggtorrent.com/user/login">Connexion</a></li>') === false
        );
    }

    protected function login($client, $login, $password, &$url, &$method, &$content_type, &$body, &$is_result_fetched)
    {
        $is_result_fetched = false;
        if ($client->fetch($this->url)) {
            $client->setcookies();
            $client->referer = $this->url;
            if ($client->fetch($this->url . "/user/login", "POST", "application/x-www-form-urlencoded",
                "id=" . rawurlencode($login) . "&pass=" . rawurlencode($password))) {
                $client->setcookies();
                return (true);
            }
        }
        return (false);
    }
}
