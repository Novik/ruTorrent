<?php

class TVTorrentsAccount extends commonAccount
{
	protected function isOK($client)
	{
		return(strpos($client->results, 'Password:<')===false);
	}
	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body)
	{                                                                   
		if($client->fetch( "http://tvtorrents.com" ))
		{
                        $client->setcookies();
			$client->referer = "http://tvtorrents.com";
        		if($client->fetch( "http://tvtorrents.com/login.do","POST","application/x-www-form-urlencoded", 
				"username=".rawurlencode($login)."&password=".rawurlencode($password).'&posted=true' ))
			{
				$client->setcookies();
				return(true);
			}
		}
		return(false);
	}
	public function test($url)
	{
		return(preg_match( "`http://tvtorrents.com/`si", $url ));
	}
}

?>