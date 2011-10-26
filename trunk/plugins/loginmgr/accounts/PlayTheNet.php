<?php

class PlayTheNetAccount extends commonAccount
{
	protected function isOK($client)
	{
		return(strpos($client->results, '<input type="password" name="pass"')===false);
	}
	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body,&$is_result_fetched)
	{                                                                   
	        $is_result_fetched = false;
		if($client->fetch( "https://www.play-the.net/?section=LOGIN&Func=access_denied" ))
		{
                        $client->setcookies();
			$client->referer = "https://www.play-the.net/?section=LOGIN&Func=access_denied";
        		if($client->fetch( "https://www.play-the.net/?section=LOGIN&type=0","POST","application/x-www-form-urlencoded", 
				"user=".rawurlencode($login)."&pass=".rawurlencode($password)."&Connexion=%C9tablir+la+Connexion" ))
			{
				$client->referer = "https://www.play-the.net/?section=LOGIN&type=0";
				$client->setcookies();
				return(true);
			}
		}
		return(false);
	}
	public function test($url)
	{
		return(preg_match( "/^http(s)?:\/\/www\.play\-the\.net\//si", $url ));
	}
}

?>