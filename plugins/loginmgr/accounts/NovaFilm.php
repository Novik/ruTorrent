<?php

class NovaFilmAccount extends commonAccount
{
	protected function isOK($client)
	{
		return(strpos($client->results, 'name="password')===false);
	}
	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body,&$is_result_fetched)
	{
		$is_result_fetched = false;
		$client->referer = "http://novafilm.tv";
       		if($client->fetch( "http://novafilm.tv/auth/login","POST","application/x-www-form-urlencoded", 
			"username=".rawurlencode($login)."&password=".rawurlencode($password) ))
		{
			$client->setcookies();
			return(true);
		}
		return(false);
	}
	public function test($url)
	{
		return(preg_match( "`http://novafilm.tv/download/`si", $url ));
	}
}

?>