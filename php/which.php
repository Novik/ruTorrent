<?php
require_once( 'cache.php' );

class WhichCache
{
	public $hash = "which.dat";
	public $changed = false;
	private $filePath = array();
	
	public function __construct($diagnostic)
	{
		if($diagnostic)
			$this->pruneCache();		
	}
	
	public function getFilePath($exe)
	{
		if(!$this->isFilePathSet($exe))
		{
			if($this->setFilePath($exe))
				return($this->filePath[$exe]);
			
			return false;
		}
		return($this->filePath[$exe]);
	}
	
	private function isFilePathSet($exe)
	{
		return(isset($this->filePath[$exe]) && !empty($this->filePath[$exe]));
	}
	
	private function setFilePath($exe)
	{
		$this->changed = true;
		$this->filePath[$exe] = exec('command -v '.$exe);
		return(is_executable($this->filePath[$exe]));
	}
	
	private function pruneCache()
	{
		foreach ($this->filePath as $key => $value)
		{
			if(!is_executable($value))
			{
				unset($this->filePath[$key]);
			}
		}
	}
}

class WhichInstance
{
	private static $instance = null;
	private static $cache = null;
	
	public static function load($diagnostic)
	{
		if (is_null(self::$cache))
			self::$cache = new rCache();
		
		if (is_null(self::$instance))
		{
			self::$instance = new WhichCache($diagnostic);
			self::$cache->get(self::$instance);
		}		
		return(self::$instance);
	}
	
	public static function save()
	{
		if (self::$instance->changed)
		{
			self::$instance->changed = false;
			self::$cache->set(self::$instance);
		}
	}
}

function findEXE( $exe )
{
	global $pathToExternals;
	if(isset($pathToExternals[$exe]) && !empty($pathToExternals[$exe]))
		return(is_executable($pathToExternals[$exe]) ? $pathToExternals[$exe] : false);
	
	global $do_diagnostic;
	return(WhichInstance::load($do_diagnostic)->getFilePath($exe));
}
