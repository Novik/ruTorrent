<?php
class AnimeBytesAccount extends commonAccount
{
	public $url = "https://animebytes.tv";
	protected function isOK($client)
	{
		return(strpos($client->results, 'name="password')===false);
	}
	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body,&$is_result_fetched)
	{
		$is_result_fetched = false;
		$client->referer = $this->url;
       		if($client->fetch( $this->url."/user/login","POST","application/x-www-form-urlencoded", 
			"username=".rawurlencode($login)."&password=".rawurlencode($password) ))
		{
			$client->setcookies();
			return(true);
		}
		return(false);
	}
}
