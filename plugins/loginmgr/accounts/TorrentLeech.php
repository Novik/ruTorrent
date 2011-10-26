<?php

class TorrentLeechAccount extends commonAccount
{
	protected function isOK($client)
	{
		return(strpos($client->results, '>Password')===false);
	}
	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body,&$is_result_fetched)
	{
		$is_result_fetched = false;
		if($client->fetch( "http://www.torrentleech.org" ))
		{
                        $client->setcookies();
			$client->referer = "http://www.torrentleech.org";
        		if($client->fetch( "http://www.torrentleech.org/user/account/login","POST","application/x-www-form-urlencoded", 
				"username=".rawurlencode($login)."&password=".rawurlencode($password).'&login=submit' ))
			{
				$client->setcookies();
				return(true);
			}
		}
		return(false);
	}
	public function test($url)
	{
		return(preg_match( "`http://www.torrentleech.org/`si", $url ));
	}
}

?>