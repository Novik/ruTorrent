<?php

class HDDreamAccount extends commonAccount
{
	protected function isOK($client)
	{
		return((strpos($client->results, '<input type="password" name="password" class="inputPassword"')===false) &&
			(strpos($client->results, '<title>An error has occured! => IP:')===false));
	}
	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body)
	{                                                                   
		if($client->fetch( "http://hd-dream.net/login.php" ))
		{
                        $client->setcookies();
			$client->referer = "http://hd-dream.net/login.php";
        		if($client->fetch( "http://hd-dream.net/takelogin.php","POST","application/x-www-form-urlencoded", 
				"logout=yes&username=".rawurlencode($login)."&password=".rawurlencode($password) ))
			{
				$client->setcookies();
				return(true);
			}
		}
		return(false);
	}
	public function test($url)
	{
		return(preg_match( "/^http:\/\/hd-dream\.net\//si", $url ));
	}
}

?>