<?php

class ILoveTorrentsAccount extends commonAccount
{
	protected function isOK($client)
	{
		return(strpos($client->results, '>Not logged in!<')===false);
	}
	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body)
	{                                                                   
		if($client->fetch( "http://www.ilovetorrents.com/login.php" ))
		{
                        $client->setcookies();
			$client->referer = "http://www.ilovetorrents.com/login.php";
        		if($client->fetch( "http://www.ilovetorrents.com/takelogin.php","POST","application/x-www-form-urlencoded", 
				"username=".rawurlencode($login)."&password=".rawurlencode($password) ))
			{
				$client->setcookies();
				return(true);
			}
		}
		return(false);
	}
	public function test($url)
	{
		return(preg_match( "`http://www.ilovetorrents.com/`si", $url ));
	}
}

?>