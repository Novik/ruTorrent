<?php

class ABTorrentsAccount extends commonAccount
{
	public $url = "https://abtorrents.me";

	protected function isOK($client)
	{
		return((strpos($client->results, 'name="password')===false) &&
			(strpos($client->results, '<b>Error</b>')===false) &&
			(strpos($client->results, '>Oops<')===false));
	}

	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body,&$is_result_fetched)
	{
		$is_result_fetched = false;
		if($client->fetch( $this->url."/login.php" ))
		{
			$client->setcookies();
			$client->referer = $this->url."/login.php";
			if($client->fetch( $this->url."/takelogin.php","POST","application/x-www-form-urlencoded",
				"username=".rawurlencode($login)."&password=".rawurlencode($password)."&submitme=X" ))
			{
				$client->setcookies();
				return(true);
			}
		}
		return(false);
	}
}
