<?php

/*
 *@author AceP1983
 *@version $Id$
*/

class BitGamerAccount extends commonAccount
{
        protected function isOK($client)
        {
                return(strpos($client->results, '<form id="loginform" method="post"')===false);
        }
        protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body,&$is_result_fetched)
        {
                $is_result_fetched = false;
                if($client->fetch( "https://bitgamer.su/login.php" ))
                {
                        $client->setcookies();
                        $client->referer = "https://bitgamer.su/login.php";

                        if($client->fetch( "https://bitgamer.su/login.php","POST","application/x-www-form-urlencoded",
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

?>