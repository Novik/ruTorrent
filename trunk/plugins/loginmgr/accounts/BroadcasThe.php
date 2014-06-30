<?php

class BroadcasTheAccount extends commonAccount
{
	public $url = "https://broadcasthe.net";

	protected function isOK($client)
	{
		return(strpos($client->results, '<form name="loginform" id="loginform" method="post"')===false);
	}
	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body,&$is_result_fetched)
	{                                                                   
	        $is_result_fetched = false;
                if($client->fetch( $this->url ))
                {
                        $client->setcookies();
			$client->referer = $this->url."/login.php";
        		if($client->fetch( $this->url."/login.php","POST","application/x-www-form-urlencoded", 
				"username=".rawurlencode($login)."&password=".rawurlencode($password)."&keeplogged=1&login=Log+In%21" ))
                        {
                                $client->setcookies();
                                return(true);
                        }
                }	        

/*	        
		if($client->fetch( $this->url ) &&
			($client->status==503) &&
			preg_match( '`name="jschl_vc" value="(?P<vc>[^"]+)"`si', $client->results, $matches ) &&
			preg_match( '`a\.value = (?P<n1>\d+)\+(?P<n2>\d+)\*(?P<n3>\d+);`si', $client->results, $digits ))
		{
			$ans = intval($digits['n1'])+intval($digits['n2'])*intval($digits['n3'])+15;
			$client->setcookies();
			$client->referer = $this->url;
			if($client->fetch( $this->url."/cdn-cgi/l/chk_jschl?jschl_vc=".$matches["vc"]."&jschl_answer=".$ans ) &&
				($client->status==200))
			{
	                        $client->setcookies();
				$client->referer = $this->url."/login.php";
	        		if($client->fetch( $this->url."/login.php","POST","application/x-www-form-urlencoded", 
					"username=".rawurlencode($login)."&password=".rawurlencode($password)."&keeplogged=1&login=Log+In%21" ) &&
					($client->status==200))
				{
					$client->setcookies();
					return(true);
				}
			}
		}
*/		
		return(false);
	}
        public function test($url)
        {
                return(preg_match( "`^http(s)?://broadcasthe.net`si", $url ));
        }
}
