<?php

class jpopsukiAccount extends commonAccount
{
	protected function isOK($client)
	{
		return(strpos($client->results, '>Password')===false);
	}
	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body,&$is_result_fetched)
	{
		$is_result_fetched = false;
		if($client->fetch( "http://jpopsuki.eu/login.php" ))
		{
                        $client->setcookies();
			$client->referer = "http://jpopsuki.eu/login.php";
        		if($client->fetch( "http://jpopsuki.eu/login.php","POST","application/x-www-form-urlencoded", 
				"username=".rawurlencode($login)."&password=".rawurlencode($password).'&login=Log+In%21' ))
			{
				$client->setcookies();
				return(true);
			}
		}
		return(false);
	}
	public function test($url)
	{
		return(preg_match( "`http://jpopsuki.eu/`si", $url ));
	}
}

?>