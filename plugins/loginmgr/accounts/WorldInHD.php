<?php

class WorldInHDAccount extends commonAccount
{
	public $url = "https://world-in-hd.net";

	protected function isOK($client)
	{
		return(strpos($client->results, 'type="password"')===false);
	}
	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body,&$is_result_fetched)
	{                                                                   
	        $is_result_fetched = false;
		if($client->fetch( $this->url ))
		{
                        $client->setcookies();
			$client->cookies["ts_language"] = "english";
			$client->referer = $this->url;
        		if($client->fetch( $this->url."/takelogin.php","POST","application/x-www-form-urlencoded", 
				"username=".rawurlencode($login)."&password=".rawurlencode($password) ))
			{
				$client->setcookies();
				$client->cookies["ts_language"] = "english";
				return(true);
			}
		}
		return(false);
	}
}
