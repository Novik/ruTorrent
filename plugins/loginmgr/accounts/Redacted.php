<?php

class RedactedAccount extends commonAccount
{
	// The tracker's origin is not written here: it is $redactedOrigin in
	// plugins/loginmgr/conf.php, and this is that origin as
	// "https://host[:port]", or '' while none is configured.
	public $url = '';

	// Why $redactedOrigin is not usable, for the log line, or '' when it is.
	private $originError = '';

	public function __construct()
	{
		global $redactedOrigin;
		$this->url = self::configuredOrigin(isset($redactedOrigin) ? $redactedOrigin : '', $this->originError);
	}

	protected function unconfigured()
	{
		if($this->url!=='')
			return('');
		return(self::unconfiguredOrigin('redactedOrigin',$this->originError));
	}

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

		if($client->fetch($this->url."/login.php","POST","application/x-www-form-urlencoded",
				"username=".rawurlencode($login)."&password=".rawurlencode($password)."&keeplogged=1&login=Login" ))
			{
				$client->setcookies();
				return(true);
			}
		}
		return(false);
	}
}
