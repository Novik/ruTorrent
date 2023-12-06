<?php

class TorrentLeechAccount extends commonAccount
{
	public $url = "https://www.torrentleech.org";

	protected function isOK($client)
	{
		return(strpos($client->results, '<input type="password"')===false);
	}
	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body,&$is_result_fetched)
	{
		$is_result_fetched = false;
		if($client->fetch( $this->url ))
		{
                        $client->setcookies();
			$client->referer = $this->url;
        		if($client->fetch( $this->url."/user/account/login","POST","application/x-www-form-urlencoded",
				"username=".rawurlencode($login)."&password=".rawurlencode($password).'&login=submit' ))
			{
				$client->setcookies();
				return(true);
			}
		}
		return(false);
	}
}
