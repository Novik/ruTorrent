<?php

/*
 *@author AceP1983
*/

class BitGamerAccount extends commonAccount
{
	public $url = "https://bitgamer.su";

        protected function isOK($client)
        {
                return(strpos($client->results, '<form id="loginform" method="post"')===false);
        }
        protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body,&$is_result_fetched)
        {
                $is_result_fetched = false;
                if($client->fetch( $this->url."/login.php" ))
                {
                        $client->setcookies();
                        $client->referer = $this->url."/login.php";

                        if($client->fetch( $this->url."/login.php","POST","application/x-www-form-urlencoded",
                                "username=".rawurlencode($login)."&password=".rawurlencode($password)."&returnto=&submit=Login" ))
                        {
                                $client->setcookies();
                                return(true);
                        }
                }
                return(false);
        }
        public function test($url)
        {
                return(preg_match( "/^http(s)?:\/\/www\.bitgamer\.su\//si", $url ));
        }
}
