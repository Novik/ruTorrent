<?php

class FrenchTorrentDBAccount extends commonAccount
{
	protected function isOK($client)
	{
		return(strpos($client->results, '<input name="password" value="" type="password"')===false);
	}
	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body)
	{                                                                   
		if($client->fetch( "http://www.frenchtorrentdb.com/?section=LOGIN&Func=access_denied" ))
		{
                        $client->setcookies();
			$client->referer = "http://www.frenchtorrentdb.com/?section=LOGIN&Func=access_denied";
        		if($client->fetch( "http://www.frenchtorrentdb.com/?section=LOGIN","POST","application/x-www-form-urlencoded", 
				"username=".rawurlencode($login)."&password=".rawurlencode($password)."&Connexion" ))
			{
				$client->referer = "http://www.frenchtorrentdb.com/?section=LOGIN";
				$client->setcookies();
				return(true);
			}
		}
		return(false);
	}
	public function test($url)
	{
		return(preg_match( "/^http(s)?:\/\/www\.frenchtorrentdb\.com\//si", $url ));
	}
}

?>