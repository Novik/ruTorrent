<?php

class LostFilmAccount extends commonAccount
{
	public $url = "http://lostfilm.tv";

	protected function isOK($client)
	{
		return(strpos($client->results, '<input type="password"')===false);
	}
	protected function isOKPostFetch($client,$url,$method,$content_type,$body)
	{
		if(	preg_match("`/download\.php\?id=(\d+)&`si", $url, $matches) &&
			preg_match("`/browse.php\?cat=`si", $client->lastredirectaddr) &&
			$client->fetch($this->url."/details.php?id=".$matches[1]) &&
			preg_match("`/download\.php\?id=".$matches[1]."&\S+\s*\sonMouseOver=\"setCookie\('dlt','([^']*)'`si", $client->results, $md5))
		{
			$client->cookies["dlt_2"] = $md5[1];
			return($client->fetch($url,$method,$content_type,$body) && ($client->get_filename()!==false));
		} 
		return(true);
	}
	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body,&$is_result_fetched)
	{
		$is_result_fetched = false;
		if($client->fetch( $this->url ))
		{
			$client->setcookies();
			$client->referer = $this->url;
        		if($client->fetch( $this->url."/useri.php","POST","application/x-www-form-urlencoded", 
				"FormLogin=".rawurlencode($login)."&FormPassword=".rawurlencode($password).'&module=1&repage=user&act=login' ))
			{
				$client->setcookies();
				return(true);
			}
		}
		return(false);
	}
	public function test($url)
	{
		return( stripos( $url, $this->url."/download.php" )===0 );
	}
}
