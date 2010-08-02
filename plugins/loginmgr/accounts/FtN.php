<?php

class FtNAccount extends commonAccount
{
	protected function isOK($client)
	{
		return((strpos($client->results, '<input name="password" type="password"')===false) &&
			(strpos($client->results, '<h2>Error</h2>')===false));
	}
	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body)
	{                                                                   
		if($client->fetch( "https://feedthe.net/" ))
		{
                        $client->setcookies();
			$client->referer = "https://feedthe.net/";
        		if($client->fetch( "https://feedthe.net/takelogin.php","POST","application/x-www-form-urlencoded", 
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
		return(preg_match( "/^https:\/\/feedthe\.net\//si", $url ));
	}
}

?>