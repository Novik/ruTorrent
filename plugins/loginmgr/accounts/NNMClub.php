<?php

class NNMClubAccount extends commonAccount
{
	public $url = "http://nnm-club.ru";

	protected function isOK($client)
	{
		return(strpos( $client->results, ' class="mainmenu">¬ход</a>' )==false);
	}
	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body,&$is_result_fetched)
	{
		$is_result_fetched = false;
		$redirect = 'index.php';

		if($client->fetch( $this->url."/forum/login.php","POST","application/x-www-form-urlencoded", 
			"redirect=".rawurlencode($redirect)."&username=".rawurlencode($login)."&password=".rawurlencode($password)."&autologin=on&login=%C2%F5%EE%E4" ))
		{
			$client->setcookies();
			return(true);
		}
		return(false);
	}
	public function test($url)
	{
		return(preg_match( "/(\.|\/)nnm-club.ru\/forum\//si", $url ) && !preg_match( "/(\.|\/)nnm-club.ru\/forum\/login.php/si", $url ));
	}
}
