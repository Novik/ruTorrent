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
		// No redirect= field: this account leaves $is_result_fetched false, so
		// the requested page is fetched separately once the session exists, and
		// the answer to the login itself is only read by isOK(). NNMClub, the
		// other account on the same forum software, posts the form without it.
		$is_result_fetched = false;
		if($client->fetch( $this->url."/login.php","POST","application/x-www-form-urlencoded",
			"username=".rawurlencode($login)."&password=".rawurlencode($password)."&login=%D0%92%D1%85%D1%96%D0%B4&autologin=on&ssl=on" ))
		{
			$client->setcookies();
			return(true);
		}
		return(false);
	}
	// Matched against the URL's host, not against the URL string: the pattern
	// this replaced also accepted https://evil.test/path/toloka.to/x, whose
	// host is the attacker's, and loginmgr would then have sent this account's
	// cookies there.
	public function test($url)
	{
		return(self::urlAddresses($url,array("toloka.to")));
	}
}
