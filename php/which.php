<?php
require_once( 'cache.php' );

class WhichCache
{
	public $hash = "which.dat";
	public $modified = false;
	public $changed = true;
	private $filePath = array();
	
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
		$this->filePath[$exe] = exec('command -v '.$exe);
		$this->changed = is_executable($this->filePath[$exe]);
		return($this->changed);
	}
	
	public function pruneCache()
	{
		foreach ($this->filePath as $key => $value)
		{
			if(!is_executable($value))
			{
				unset($this->filePath[$key]);
				$this->changed = true;
			}
		}
	}
}

class WhichInstance
{
	private static $instance = null;
	private static $cache = null;
	
	public static function load()
	{
		if (!self::initialized())
		{
			self::$cache = new rCache();
			self::$instance = new WhichCache();
			self::$cache->get(self::$instance);
			self::$instance->pruneCache();
		}
		return(self::$instance);
	}
	
	public static function save()
	{
		if (self::initialized() && self::$instance->changed)
		{
			self::$instance->changed = false;
			self::$cache->set(self::$instance);
		}
	}
	
	private static function initialized()
	{
		return (!is_null(self::$instance) && !is_null(self::$cache));
	}
}

function findEXE( $exe )
{
	global $pathToExternals;
	if(isset($pathToExternals[$exe]) && !empty($pathToExternals[$exe]))
		return(is_executable($pathToExternals[$exe]) ? $pathToExternals[$exe] : false);
	
	return(WhichInstance::load()->getFilePath($exe));
}
