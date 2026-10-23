<?php

class TorrentechAccount extends commonAccount
{
	public $url = "https://www.torrentech.org";

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
			// The session id is read out of a response body and spliced into
			// the next request's target, which Snoopy writes by hand. A
			// request target carries no whitespace and no control character:
			// a space ends the target as far as a server's parser is
			// concerned, so a request line carrying one has a part too many.
			// The required literal after the capture means a session id with
			// such a byte matches nothing, and no credentials are posted --
			// a prefix of a session id names a different session.
			if(preg_match('`<form action="'.$this->url.'/index.php\?s=(?P<s>[^[:space:][:cntrl:]]+)&amp;act=Login&amp;CODE=01&amp;CookieDate=1`',$client->results, $matches) &&
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
