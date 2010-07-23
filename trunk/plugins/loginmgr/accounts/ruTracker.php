<?php

class ruTrackerAccount extends commonAccount
{
	protected function isOK($client)
	{
		return(strpos( $client->results, '/<input name="login_password"/si' )==false);
	}
	protected function updateCached($client,$url)
	{
		$id = $this->getDownloadId($url);
                if($id!==false)
		{
			$client->referer = "http://rutracker.org/forum/viewtopic.php?t=".$id;	
			$client->cookies["bb_dl"]=$id;
		}
		return(true);
	}
	protected function getDownloadId($url)
	{
		if(preg_match( "/(\.|)rutracker.org\/forum\/dl\.php\?t=(?P<id>\d+)$/si", $url, $matches ))
			return($matches["id"]);
		return(false);
	}
	protected function login($client,$login,$password,$url)
	{                                                                   
		$id = $this->getDownloadId($url);
		if($id===false)
		{
			$redirect = "/forum/index.php";
			$referer = "http://rutracker.org/forum/index.php";
		}
		else
		{
			$redirect = "http://rutracker.org/forum/viewtopic.php?t=".$id;
			$referer = "http://rutracker.org/forum/viewtopic.php?t=".$id;
		}
		if($client->fetch( "http://login.rutracker.org/forum/login.php","POST","application/x-www-form-urlencoded", 
			"redirect=".rawurlencode($redirect)."&login_username=".rawurlencode($login)."&login_password=".rawurlencode($password)."&login=%C2%F5%EE%E4" ))
		{
			$client->setcookies();
			$client->referer = $referer;
			if($id!==false)
				$client->cookies["bb_dl"]=$id;
			return(true);
		}
		return(false);
	}
	public function test($url)
	{
		return(preg_match( "/(\.|)rutracker.org\/forum\//si", $url ));
	}
}

?>