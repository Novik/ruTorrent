<?php

class WorldInHDAccount extends commonAccount
{
	protected function isOK($client)
	{
		return(strpos($client->results, 'type="password"')===false);
	}
	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body)
	{                                                                   
		if($client->fetch( "https://world-in-hd.net" ))
		{
                        $client->setcookies();
			$client->cookies["ts_language"] = "english";
			$client->referer = "https://world-in-hd.net";
        		if($client->fetch( "https://world-in-hd.net/takelogin.php","POST","application/x-www-form-urlencoded", 
				"username=".rawurlencode($login)."&password=".rawurlencode($password) ))
			{
				$client->setcookies();
				$client->cookies["ts_language"] = "english";
				return(true);
			}
		}
		return(false);
	}
	public function test($url)
	{
		return(preg_match( "`https://world-in-hd.net`si", $url ));
	}
}

?>