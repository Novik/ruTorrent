<?php

class PlayTheNetAccount extends commonAccount
{
	protected function isOK($client)
	{
		return(strpos($client->results, '<input type="password" name="pass"')===false));
	}
	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body)
	{                                                                   
		if($client->fetch( "https://www.play-the.net/?section=LOGIN&Func=access_denied" ))
		{
                        $client->setcookies();
			$client->referer = "https://www.play-the.net/?section=LOGIN&Func=access_denied";
        		if($client->fetch( "https://www.play-the.net/?section=LOGIN&type=0","POST","application/x-www-form-urlencoded", 
				"user=".rawurlencode($login)."&pass=".rawurlencode($password)."&Connexion=%C9tablir+la+Connexion" ))
			{
				$client->setcookies();
				return(true);
			}
		}
		return(false);
	}
	public function test($url)
	{
		return(preg_match( "/^https:\/\/www\.play\-the\.net\//si", $url ));
	}
}

?>