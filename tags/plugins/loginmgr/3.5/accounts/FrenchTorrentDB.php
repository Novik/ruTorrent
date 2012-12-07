<?php

class FrenchTorrentDBAccount extends commonAccount
{
	public $url = "http://www.frenchtorrentdb.com";

	protected function isOKPostFetch($client,$url,$method,$content_type,$body)
	{
		if(	preg_match("`/\?section=DOWNLOAD`si", $url) &&
			preg_match("`/\?section=INFOS`si", $client->lastredirectaddr) &&
			preg_match('`<a class="dl_link" href="([^"]*)"`si', $client->results, $match))
			return($client->fetch($this->url.$match[1]) && ($client->get_filename()!==false));
		return($this->isOK($client));
	}
	protected function isOK($client)
	{
		return(strpos($client->results, '<input name="password" value="" type="password"')===false);
	}
	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body,&$is_result_fetched)
	{                                                                   
		if($client->fetch( $this->url."/?section=LOGIN&Func=access_denied" ))
		{
                        $client->setcookies();
			$client->referer = $this->url."/?section=LOGIN&Func=access_denied";
        		if($client->fetch( $this->url."/?section=LOGIN","POST","application/x-www-form-urlencoded", 
				"username=".rawurlencode($login)."&password=".rawurlencode($password)."&Connexion" ))
			{
				$client->referer = $this->url."/?section=LOGIN";
				$client->setcookies();
				return(true);
			}
		}
		return(false);
	}
	public function test($url)
	{
		return(preg_match( "/^http(s)?:\/\/www\.frenchtorrentdb\.com\//si", $url ));
	}
}
