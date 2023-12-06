<?php

class ZamundaNetAccount extends commonAccount
{
	public $url = "https://zamunda.net";

	protected function isOK($client)
	{
		return(strpos($client->results, '<input type="password"')===false);
	}
	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body,&$is_result_fetched)
	{
		$is_result_fetched = false;
		if($client->fetch( $this->url."/langchange.php?lang=en" ))
		{
			$client->setcookies();
			if($client->fetch( $this->url."/takelogin.php","POST","application/x-www-form-urlencoded",
				"username=".rawurlencode($login)."&password=".rawurlencode($password) ))
			{
				$client->setcookies();
				return(true);
			}
		}
		return(false);
	}
	public function test($url)
	{
		return(preg_match( "/(\.|\/)zamunda\.(net|ch)\//si", $url ));
	}
}
