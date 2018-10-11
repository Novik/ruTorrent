<?php

class YggTorrentAccount extends commonAccount
{
    private $some_old_url = "https://www.yggtorrent.to";
    public $url;
    public function __construct()
	{
		$this->url = $this->get_redirect_final_target($this->some_old_url);
	}
    protected function isOK($client)
    {
        return (
            $client->status == 200
            && $client->results !== 'Vous devez vous connecter pour télécharger un torrent'
            && strpos($client->results, "S'identifier</a>") === false
        );
    }

    protected function login($client, $login, $password, &$url, &$method, &$content_type, &$body, &$is_result_fetched)
    {
        $is_result_fetched = false;
        if ($client->fetch($url)) {
            $client->setcookies();
            $client->referer = $url;
            if ($client->fetch($this->url . "/user/login", "POST", "application/x-www-form-urlencoded",
                "id=" . rawurlencode($login) . "&pass=" . rawurlencode($password) . '&submit=')) {
                $client->setcookies();
                return (true);
            }
        }
        return (false);
    }
	private function get_redirect_final_target($url)
	{
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_NOBODY, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // follow redirects
		curl_setopt($ch, CURLOPT_AUTOREFERER, 1); // set referer on redirect
		curl_exec($ch);
		$target = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
		curl_close($ch);

		if ($target)
			return $target;

		return false;
	}
}
