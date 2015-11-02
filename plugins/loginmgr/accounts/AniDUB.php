<?php

class AniDUBAccount extends commonAccount
{
    public $url = "http://tr.anidub.com";

    protected function isOK($client)
    {
        return(strpos($client->results, '<input type="text" name="login_name" id="login_name">')===false);
    }
    protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body,&$is_result_fetched)
    {
        $is_result_fetched = false;
        if($client->fetch( $this->url."/" ))
        {
            $client->setcookies();
            $client->referer = $this->url."/";

            if($client->fetch( $this->url."/","POST","application/x-www-form-urlencoded",
                "login_name=".rawurlencode($login)."&login_password=".rawurlencode($password)."&login=submit" ))
            {
                $client->setcookies();
                return(true);
            }
        }
        return(false);
    }
    public function test($url)
    {
        return(preg_match( "/^http:\/\/tr\.anidub\.com\//si", $url ));
    }
}
