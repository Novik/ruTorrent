<?php

class TVTorrentsAccount extends commonAccount
{
	public $url = "http://tvtorrents.com";

	protected function isOK($client)
	{
		return(strpos($client->results, 'Password:<')===false);
	}
	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body,&$is_result_fetched)
	{
		$is_result_fetched = false;
		if($client->fetch( $this->url ))
		{
                        $client->setcookies();
			$client->referer = $this->url;
        		if($client->fetch( $this->url."/login.do","POST","application/x-www-form-urlencoded", 
				"username=".rawurlencode($login)."&password=".rawurlencode($password).'&posted=true' ))
			{
				$client->setcookies();
				return(true);
			}
		}
		return(false);
	}
}
