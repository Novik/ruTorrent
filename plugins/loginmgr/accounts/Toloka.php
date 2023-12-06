<?php

// Toloka.to support by ReMMeR github@r3mm3r.net

class tolokaAccount extends commonAccount
{
	public $url = "https://toloka.to";

	protected function isOK($client)
	{
		return(strpos( $client->results, ' href="/profile.php?mode=register"' )==false);
	}
	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body,&$is_result_fetched)
	{
		$is_result_fetched = false;
		if($client->fetch( $this->url."/login.php","POST","application/x-www-form-urlencoded",
			"redirect=".rawurlencode($redirect)."&username=".rawurlencode($login)."&password=".rawurlencode($password)."&login=%D0%92%D1%85%D1%96%D0%B4&autologin=on&ssl=on" ))
		{
			$client->setcookies();
			return(true);
		}
		return(false);
	}
	public function test($url)
	{
		return(preg_match( "/(\.|\/)toloka.to\//si", $url ));
	}
}
