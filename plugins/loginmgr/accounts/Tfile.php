<?php

class TfileAccount extends commonAccount
{
	// The tracker's origin is not written here: it is $tfileOrigin in
	// plugins/loginmgr/conf.php, and this is that origin as
	// "https://host[:port]", or '' while none is configured.
	public $url = '';

	// Why $tfileOrigin is not usable, for the log line, or '' when it is.
	private $originError = '';

	public function __construct()
	{
		global $tfileOrigin;
		$this->url = self::configuredOrigin(isset($tfileOrigin) ? $tfileOrigin : '', $this->originError);
	}

	protected function unconfigured()
	{
		if($this->url!=='')
			return('');
		return(self::unconfiguredOrigin('tfileOrigin',$this->originError));
	}

	protected function isOK($client)
	{
		return(strpos($client->results, '<input type="password"')===false);
	}
	protected function login($client,$login,$password,&$url,&$method,&$content_type,&$body,&$is_result_fetched)
	{
		$is_result_fetched = false;
		if($client->fetch( $this->url."/forum/login.php" ))
		{
			$client->setcookies();
			$client->referer = $this->url."/forum/login.php";
			if($client->fetch( $this->url."/forum/login.php","POST","application/x-www-form-urlencoded",
				"username=".rawurlencode($login)."&password=".rawurlencode($password)."&login=%C2%F5%EE%E4" ))
			{
				$client->setcookies();
				return(true);
			}
		}
		return(false);
	}
	// The forum of the configured origin, and nothing else. Matched against
	// the URL's host, not against the URL string, which would also accept
	// https://evil.test/path/<host>/forum/x -- whose host is the attacker's,
	// and loginmgr would hand this account's cookies to whoever runs it.
	public function test($url)
	{
		if($this->refuseUnconfigured())
			return(false);
		$site = parse_url($this->url);
		return(self::urlAddresses($url,array($site["host"]),$site["scheme"],"/forum/"));
	}
}
