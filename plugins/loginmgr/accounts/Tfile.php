<?php

class TfileAccount extends commonAccount
{
	public $url = "https://megatfile.cc";

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
	// Matched against the URL's host, not against the URL string: the pattern
	// this replaced also accepted https://evil.test/path/megatfile.cc/forum/x, whose
	// host is the attacker's, and loginmgr would then have sent this account's
	// cookies there.
	public function test($url)
	{
		return(self::urlAddresses($url,array("megatfile.cc"),"https","/forum/"));
	}
}
