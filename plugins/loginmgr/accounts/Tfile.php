<?php

class TfileAccount extends commonAccount
{
	public $url = "http://tfile-video.org";

	protected function isOK($client)
	{
		return(strpos($client->results, '<input type="password"')===false);
	}
	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body,&$is_result_fetched)
	{
		$is_result_fetched = false;
		if($client->fetch( $this->url."/forum/login.php" ))
		{
			$client->setcookies();
			$client->referer = $this->url."/forum/login.php";
			if($client->fetch( $this->url."/forum/login.php","POST","application/x-www-form-urlencoded",
				"username=".rawurlencode($login)."&password=".rawurlencode($password)."&login=%C2%F5%EE%E4" ))
			{
				$client->setcookies();
				return(true);
			}
		}
		return(false);
	}
	public function test($url)
	{
		return(preg_match( "/(\.|\/)(tfile|tfile-video|tfile-music).(me|co|cc|org)\/forum\//si", $url ));
	}
}
