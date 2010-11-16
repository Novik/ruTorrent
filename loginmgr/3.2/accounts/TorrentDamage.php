<?php

class TorrentDamageAccount extends commonAccount
{
	protected function isOK($client)
	{
		return(strpos($client->results, '>Password<')===false);
	}
	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body)
	{                                                                   
		if($client->fetch( "http://www.torrent-damage.net/login.php" ))
		{
                        $client->setcookies();
			$client->referer = "http://www.torrent-damage.net/login.php";
        		if($client->fetch( "http://www.torrent-damage.net/login.php","POST","application/x-www-form-urlencoded", 
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
		return(preg_match( "`http://www.torrent-damage.net/`si", $url ));
	}
}

?>