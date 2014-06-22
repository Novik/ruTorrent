<?php

class TorrentechAccount extends commonAccount
{
	public $url = "http://www.torrentech.org";

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
			if(preg_match('`<form action="'.$this->url.'/index.php\?s=(?P<s>.+)&amp;act=Login&amp;CODE=01&amp;CookieDate=1`',$client->results, $matches) &&
				$client->fetch( $this->url."/index.php?s=".$matches['s']."&act=Login&CODE=01&CookieDate=1","POST","application/x-www-form-urlencoded", 
				"UserName=".rawurlencode($login)."&PassWord=".rawurlencode($password) ))
			{
				$client->setcookies();
				return(true);
			}
		}			
		return(false);
	}
}
