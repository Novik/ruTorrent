<?php

class ruTrackerAccount extends commonAccount
{
	public $url = "https://rutracker.org";

	protected function isOK($client)
	{
		return(strpos( $client->results, ' name="login_password"' )==false);
	}
	protected function updateCached($client,&$url,&$method,&$content_type,&$body)
	{
		$id = $this->getDownloadId($url);
		if($id!==false)
		{
			$client->referer = "https://rutracker.org/forum/viewtopic.php?t=".$id;
			$client->cookies["bb_dl"]=$id;
			$method = "POST";
			$content_type = "application/x-www-form-urlencoded";
			$body = '';
		}
		return(true);
	}
	protected function getDownloadId($url)
	{
		if(preg_match( "/(\.|)rutracker.(org|cr|net|nl)\/forum\/dl\.php\?t=(?P<id>\d+)$/si", $url, $matches ))
			return($matches["id"]);
		return(false);
	}
	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body,&$is_result_fetched)
	{
		$is_result_fetched = false;
		$id = $this->getDownloadId($url);
		if($id===false)
		{
			$redirect = $url;
			$referer = "https://rutracker.org/forum/index.php";
			$is_result_fetched = true;
		}
		else
		{
			$redirect = "https://rutracker.org/forum/viewtopic.php?t=".$id;
			$referer = "https://rutracker.org/forum/viewtopic.php?t=".$id;
		}
		if($client->fetch( $this->url."/forum/login.php","POST","application/x-www-form-urlencoded",
			"redirect=".rawurlencode($redirect)."&login_username=".rawurlencode($login)."&login_password=".rawurlencode($password)."&login=%C2%F5%EE%E4" ))
		{
			$client->setcookies();
			$client->referer = $referer;
			if($id!==false)
			{
				$client->cookies["bb_dl"]=$id;
				$method = "POST";
				$content_type = "application/x-www-form-urlencoded";
				$body = '';
			}
			return(true);
		}
		return(false);
	}
	public function test($url)
	{
		return(preg_match( "/(\.|\/)rutracker\.(org|cr|net|nl)\/forum\//si", $url ));
	}
}
