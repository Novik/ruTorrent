<?php

class EliteTrackerAccount extends commonAccount
{
	public $url = "https://elite-tracker.net";

	protected function isOK($client)
	{
		return((strpos($client->results, 'name="password')===false) &&
			(strpos($client->results, '<b>Erreur</b>')===false));
	}
	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body,&$is_result_fetched)
	{
		$is_result_fetched = false;
		if($client->fetch( $this->url."/" ))
		{
                        $client->setcookies();
			$client->referer = $this->url."/";
        		if($client->fetch( $this->url."/takelogin.php","POST","application/x-www-form-urlencoded",
				"username=".rawurlencode($login)."&password=".rawurlencode($password) ))
			{
				$client->setcookies();
				return(true);
			}
		}
		return(false);
	}
}
