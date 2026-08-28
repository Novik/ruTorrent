<?php

class TapochekNetAccount extends commonAccount
{
	public $url = "https://tapochek.net";

	protected function isOK($client)
	{
		return(strpos( $client->results, ' name="login_password"' )==false);
	}
	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body,&$is_result_fetched)
	{
		$is_result_fetched = false;
		if($client->fetch( $this->url."/login.php" ))
		{
			$client->setcookies();
			$client->referer = $this->url."/login.php";
			if($client->fetch( $this->url."/login.php","POST","application/x-www-form-urlencoded",
			    "login_username=".rawurlencode($login)."&login_password=".rawurlencode($password)."&login=%C2%F5%EE%E4" ))
			{
				$client->setcookies();
				return(true);
			}
		}
		return(false);
	}
	// Matched against the URL's host, not against the URL string: the pattern
	// this replaced also accepted https://evil.test/path/tapochek.net/x, whose
	// host is the attacker's, and loginmgr would then have sent this account's
	// cookies there.
	public function test($url)
	{
		return(self::urlAddresses($url,array("tapochek.net")));
	}
}
