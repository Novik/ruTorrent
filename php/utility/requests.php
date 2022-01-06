<?php

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
			if(isset($_SERVER['HTTP_X_FORWARDED_HOST']))
				$enabledOrigins[] = strtok($_SERVER['HTTP_X_FORWARDED_HOST'], ':');
			if(isset($_SERVER['HTTP_HOST']))
				$enabledOrigins[] = strtok($_SERVER['HTTP_HOST'], ':');
			if(isset($_SERVER['HTTP_ORIGIN']))
			{
				if($_SERVER['HTTP_ORIGIN'] == "null") # privacy-sensitive context
				{
					return;
				}
				$host = parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST);
				if(in_array($host,$enabledOrigins))
				{
					return;
				}
			}
			if(isset($_SERVER['HTTP_REFERER']))
			{
				$host = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
				if(in_array($host,$enabledOrigins))
				{
					return;
				}
			}
			header('HTTP/1.0 403 Forbidden', true, 403);
			die('Forbidden');
		}
	}
}