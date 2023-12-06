<?php

/*
 *@author AceP1983
*/

class MyAnonamouseAccount extends commonAccount
{
	public $url = "https://www.myanonamouse.net";

	protected function isOK($client)
	{
		return(($client->status!=403) && (strpos($client->results, '<input type="password"')===false));
	}
	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body,&$is_result_fetched)
        {
                $is_result_fetched = false;
                if($client->fetch( $this->url ))
                {
                        $client->setcookies();
                        $client->referer = $this->url;
			$parameters = "email=".rawurlencode($login)."&password=".rawurlencode($password);

                        if(preg_match('/ name="d" value="(?P<d>[^"]*)"/', $client->results, $match))
                        {
				$parameters.=("&d=".rawurlencode($match["d"]));
			}
                        if(preg_match('/ name="s" value="(?P<s>[^"]*)"/', $client->results, $match))
                        {
				$parameters.=("&s=".rawurlencode($match["s"]));
			}
                        if($client->fetch( $this->url."/takelogin.php","POST","application/x-www-form-urlencoded", $parameters ))
                        {
                                $client->setcookies();
                                return(!empty($client->cookies));
                        }
                }
                return(false);
        }
}