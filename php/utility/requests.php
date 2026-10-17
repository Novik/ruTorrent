<?php

require_once(__DIR__.'/../urlhost.php');

class Requests
{
	public static function disableUnsupportedMethods()
	{
		if( isset($_SERVER['REQUEST_METHOD']) &&
			($_SERVER['REQUEST_METHOD']!='GET') &&
			($_SERVER['REQUEST_METHOD']!='POST') )
		{
			header('HTTP/1.0 405 Method Not Allowed', true, 405);
			die('Method Not Allowed');
		}
	}

	public static function makeCSRFCheck()
	{
		global $enableCSRFCheck;
		if($enableCSRFCheck &&
			isset($_SERVER['REQUEST_METHOD']) &&
			($_SERVER['REQUEST_METHOD'] != "GET"))
		{
			global $enabledOrigins;
			if(empty($enabledOrigins))
				$enabledOrigins = array();
			// A Host header is an authority, host[:port], and an IPv6 host has
			// colons of its own: read up to the first colon, "[::1]:8080" became
			// "[", matched no Origin, and every POST made through an IPv6
			// address answered 403. It is read as the Origin and Referer hosts
			// below are read, and compared as a DNS name: without regard to
			// case or trailing dots, and never as a mere substring.
			if(isset($_SERVER['HTTP_X_FORWARDED_HOST']))
				$enabledOrigins[] = UrlHost::of('http://'.$_SERVER['HTTP_X_FORWARDED_HOST']);
			if(isset($_SERVER['HTTP_HOST']))
				$enabledOrigins[] = UrlHost::of('http://'.$_SERVER['HTTP_HOST']);
			if(isset($_SERVER['HTTP_ORIGIN']))
			{
				if($_SERVER['HTTP_ORIGIN'] == "null") # privacy-sensitive context
				{
					return;
				}
				if(UrlHost::isExactlyOneOf(UrlHost::of($_SERVER['HTTP_ORIGIN']),$enabledOrigins))
				{
					return;
				}
			}
			if(isset($_SERVER['HTTP_REFERER']))
			{
				if(UrlHost::isExactlyOneOf(UrlHost::of($_SERVER['HTTP_REFERER']),$enabledOrigins))
				{
					return;
				}
			}
			header('HTTP/1.0 403 Forbidden', true, 403);
			die('Forbidden');
		}
	}
}
