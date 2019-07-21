<?php

class JackettAccount extends commonAccount
{
    protected function isOK($client)
    {
        return (true);
    }

    protected function login($client, $login, $password, &$url, &$method, &$content_type, &$body, &$is_result_fetched)
    {
        $this->url = $login;
        return (true);
    }

    public function test($url)
    {
        return true;
    }
}
