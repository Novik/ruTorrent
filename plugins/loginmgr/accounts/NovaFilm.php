<?php

class NovaFilmAccount extends commonAccount
{
	public $url = "https://novafilm.tv";

	protected function isOK($client)
	{
		return(strpos($client->results, 'name="password')===false);
	}
	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body,&$is_result_fetched)
	{
		$is_result_fetched = false;
		$client->referer = $this->url;
       		if($client->fetch( $this->url."/auth/login","POST","application/x-www-form-urlencoded",
			"username=".rawurlencode($login)."&password=".rawurlencode($password) ))
		{
			$client->setcookies();
			return(true);
		}
		return(false);
	}
	public function test($url)
	{
		return(self::urlAddresses($url,array("novafilm.tv"),"https","/download/"));
	}
}
