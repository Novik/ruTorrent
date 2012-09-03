<?php

class HDDreamAccount extends commonAccount
{
	public $url = "http://hd-dream.net";

	protected function isOK($client)
	{
		return((strpos($client->results, '<input type="password" name="password" class="inputPassword"')===false) &&
			(strpos($client->results, '<title>An error has occured! => IP:')===false));
	}
	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body,&$is_result_fetched)
	{                                                                   
	        $is_result_fetched = false;
		if($client->fetch( $this->url."/login.php" ))
		{
                        $client->setcookies();
			$client->referer = $this->url."/login.php";
        		if($client->fetch( $this->url."/takelogin.php","POST","application/x-www-form-urlencoded", 
				"logout=yes&username=".rawurlencode($login)."&password=".rawurlencode($password) ))
			{
				$client->setcookies();
				return(true);
			}
		}
		return(false);
	}
}
