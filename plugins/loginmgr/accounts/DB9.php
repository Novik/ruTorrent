<?php
class DB9Account extends commonAccount
{
	public $url = "https://www.deepbassnine.com";
	protected function isOK($client)
	{
		return(strpos($client->results, '<form id="loginform"')===false);
	}
	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body,&$is_result_fetched)
	{
		$is_result_fetched = false;
		if($client->fetch( $this->url."/login.php" ))
		{
                        $client->setcookies();
			$client->referer = $this->url."/login.php";
        		if($client->fetch( $this->url."/login.php","POST","application/x-www-form-urlencoded", 
				"username=".rawurlencode($login)."&password=".rawurlencode($password)."&keeplogged=1&login=Login" ))
			{
				$client->setcookies();
				return(true);
			}
		}
		return(false);
	}
}
