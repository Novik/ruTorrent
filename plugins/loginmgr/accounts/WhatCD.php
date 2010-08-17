<?php

class WhatCDAccount extends commonAccount
{
	protected function isOK($client)
	{
		return(strpos($client->results, '<form id="loginform" method="post"')===false);
	}
	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body)
	{                                                                   
		if($client->fetch( "http://what.cd/login.php" ))
		{
                        $client->setcookies();
			$client->referer = "http://what.cd/login.php";

        		if($client->fetch( "http://what.cd/login.php","POST","application/x-www-form-urlencoded", 
				"username=".rawurlencode($login)."&password=".rawurlencode($password)."&keeplogged=1&login=Login" ))
			{
				$client->setcookies();
				return(true);
			}
		}
		return(false);
	}
	public function test($url)
	{
		return(preg_match( "/^http:\/\/what\.cd\//si", $url ));
	}
}

?>