<?php

class User
{	
	private static $userLoginInstance = null;
	private static $localModeInstance = null;
	
	public static function getLogin()
	{
		if (is_null(self::$userLoginInstance))
			self::$userLoginInstance = self::getLoginInstance();

		return self::$userLoginInstance;
	}
	
	private static function getLoginInstance()
	{
		return( (isset($_SERVER['REMOTE_USER']) && !empty($_SERVER['REMOTE_USER'])) ? 
			preg_replace( "/[^a-z0-9\-_]/", "_", strtolower($_SERVER['REMOTE_USER']) ) : '' );
	}

	public static function getUser()
	{
		global $forbidUserSettings;
		return( !$forbidUserSettings ? self::getLogin() : '' );
	}
	
	public static function isLocalMode( $host = null, $port = null )
	{
		if (is_null(self::$localModeInstance))
		{		
			global $localhosts; global $scgi_port; global $scgi_host;
			if(!isset($localhosts) || !count($localhosts))
				$localhosts = array( "127.0.0.1", "localhost" );
			if(is_null($port))
				$port = $scgi_port;
			if(is_null($host))
				$host = $scgi_host;
			self::$localModeInstance = (($port == 0) || in_array($host,$localhosts));
		}
		return self::$localModeInstance;
	}
}