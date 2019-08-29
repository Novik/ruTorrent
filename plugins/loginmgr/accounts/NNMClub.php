<?php

class NNMClubAccount extends commonAccount
{
	public $url = "https://nnmclub.to";

	protected function isOK($client)
	{
		return( (strpos( $client->results, ' class="mainmenu">¬ход</a>' )===false) &&
			 (strpos( $client->results, "document.cookie='_ddn_" )===false) );
	}
	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body,&$is_result_fetched)
	{
		$is_result_fetched = false;
		if($client->fetch( $this->url.'/forum/login.php' ) &&
			preg_match( "`document.cookie='_ddn_(?P<cname>[^=]+)=(?P<cvalue>[^;]*);`si", $client->results, $matches ))
		{
			$client->cookies = array_merge($client->cookies, array('_ddn_'.$matches["cname"]=>$matches["cvalue"]));
		}
		if($client->fetch( $this->url."/forum/login.php","POST","application/x-www-form-urlencoded",
			"&username=".rawurlencode($login)."&password=".rawurlencode($password)."&autologin=on&login=%C2%F5%EE%E4" ))
		{
			$client->setcookies();
			return(true);
		}
		return(false);
	}
	public function test($url)
	{
		return(preg_match( "/(\.|\/)(nnm-club|nnmclub)\.(ru|me|to|name|tv)\/forum\//si", $url ) && 
			!preg_match( "/(\.|\/)(nnm-club|nnmclub)\.(ru|me|to|name|tv)\/forum\/login.php/si", $url ));
	}
}
