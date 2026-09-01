<?php

class LostFilmAccount extends commonAccount
{
	// https, because login() below posts the account's password to
	// $url."/useri.php". A redirect to https afterwards does not protect a
	// POST that has already gone out in the clear -- and the site issues
	// exactly such a redirect, so http here bought nothing but the exposure.
	public $url = "https://lostfilm.tv";

	protected function isOK($client)
	{
		return(strpos($client->results, '<input type="password"')===false);
	}
	protected function isOKPostFetch($client,$url,$method,$content_type,$body)
	{
		// Taken first: the recovery below fetches details.php onto this same
		// client, and after that $client->results is that page rather than the
		// answer the caller asked for. Judging the fallthrough on it would be
		// judging the wrong response.
		$answered = $this->classifyAnswer($client);
		if(	preg_match("`/download\.php\?id=(\d+)&`si", $url, $matches) &&
			preg_match("`/browse.php\?cat=`si", (string) $client->lastredirectaddr) &&
			$client->fetch($this->url."/details.php?id=".$matches[1]) &&
			preg_match("`/download\.php\?id=".$matches[1]."&\S+\s*\sonMouseOver=\"setCookie\('dlt','([^']*)'`si", $client->results, $md5))
		{
			$client->cookies["dlt_2"] = $md5[1];
			// The retry is an answer like any other. get_filename() reads only
			// the Content-Disposition header -- never the status, never the
			// body -- so on its own it accepts a 500 page and a body Snoopy
			// failed to decompress, which are the shapes the base class exists
			// to refuse.
			return($client->fetch($url,$method,$content_type,$body) &&
				($this->classifyAnswer($client)===commonAccount::ANSWER_LIVE) &&
				($client->get_filename()!==false));
		}
		// The "nothing special to do here" branch. It has to hand the question
		// back rather than answer true on its own: this is the only override
		// of isOKPostFetch() in accounts/, so answering here was the one way
		// to skip the base class's verdict -- and on the cached path it
		// skipped isOK() with it, leaving this account checking nothing.
		return($answered===commonAccount::ANSWER_LIVE);
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
		return(self::urlAddresses($url,array("lostfilm.tv"),"https","/download.php"));
	}
}
