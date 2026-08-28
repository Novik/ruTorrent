<?php

class KinozalTVAccount extends commonAccount
{
	public $url = "https://kinozal.guru";

	protected function isOK($client)
	{
		// Two independent markers of a guest answer, because Kinozal has two
		// kinds of them. The login form is matched on the password field alone
		// (like RUTracker/TapochekNet do): the previous two-attribute probe
		// stopped matching once the markup became <input type=password size=35
		// id="password" name="password">. But get_srv_details.php answers a
		// guest with a plain "not authorized" line and no form at all, so the
		// registration link -- which every guest answer carries and no
		// authenticated one does -- is what catches that second shape.
		return(strpos($client->results, ' name="password"')===false &&
			strpos($client->results, '/signup.php')===false);
	}
	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body,&$is_result_fetched)
	{
	        $is_result_fetched = false;
		if($client->fetch( $this->url ))
		{
                        $client->setcookies();
			$client->referer = $this->url;
        		if($client->fetch( $this->url."/takelogin.php","POST","application/x-www-form-urlencoded",
				"username=".rawurlencode($login)."&password=".rawurlencode($password) ))
			{
				$client->setcookies();
				return(true);
			}
		}
		return(false);
	}
	// Matched against the URL's host, not against the URL string: the pattern
	// this replaced also accepted https://evil.test/path/kinozal.guru/x, whose
	// host is the attacker's, and loginmgr would then have sent this account's
	// cookies there.
	public function test($url)
	{
		return(self::urlAddresses($url,array("kinozal.tv","kinozal.me","kinozal.guru")));
	}
}
