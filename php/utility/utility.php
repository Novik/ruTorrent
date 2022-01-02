<?php

class Utility extends ruTorrentConfig
{	
	private static function stripSlashesFromArray(&$arr)
	{
        if(is_array($arr))
        {
			foreach($arr as $k=>$v)
			{
				if(is_array($v))
				{
					self::stripSlashesFromArray($v);
					$arr[$k] = $v;
				}
				else
				{
					$arr[$k] = stripslashes($v);
				}
			}
		}
	}

	public static function fix_magic_quotes_gpc()
	{
		if(version_compare(phpversion(), '5.4', '<'))
		{
			if(function_exists('ini_set'))
			{
				ini_set('magic_quotes_runtime', 0);
				ini_set('magic_quotes_sybase', 0);
			}
			if(get_magic_quotes_gpc())
			{
				self::stripSlashesFromArray($_POST);
				self::stripSlashesFromArray($_GET);
				self::stripSlashesFromArray($_COOKIE);
				self::stripSlashesFromArray($_REQUEST);
			}
		}
	}
	
	public static function quoteAndDeslashEachItem($item)
	{
		return('"'.addcslashes($item,"\\\'\"\n\r\t").'"'); 
	}

	public static function sortArrayTime( $a, $b )
	{
		return( ($a["time"] > $b["time"]) ? 1 : (($a["time"] < $b["time"]) ? -1 : 0) );
	}

	public static function getExternal($exe)
	{
		global $pathToExternals;
		return( (isset($pathToExternals[$exe]) && !empty($pathToExternals[$exe])) ? $pathToExternals[$exe] : $exe );
	}

	public static function getPHP()
	{
		return( self::getExternal("php") );
	}
	
	public static function makeCSRFCheck()
	{				
		if(self::enableCSRFCheck && 
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
}