<?php
require_once( 'cache.php' );

class WhichCache
{
	public $hash = "which.dat";
	private $filePath = array();
	private static $instance = null;
	
	private function __construct($diagnostic)
	{
		$cache = new rCache();
		$cache->get($this);
		
		if($diagnostic)
			$this->pruneCache();
	}
	
	public static function load($diagnostic)
	{
		if(is_null(self::$instance))
			self::$instance = new WhichCache($diagnostic);
		
		return(self::$instance);
	}
	
	public function getFilePath($exe)
	{
		if(!$this->isFilePathSet($exe))
		{
			if($this->setFilePath($exe))
			{
				$this->store($exe);
				return($this->filePath[$exe]);
			}
			return false;
		}
		return($this->filePath[$exe]);
	}
	
	private function store($exe)
	{
		$cache = new rCache();
		$cache->set(self::$instance);
	}
	
	private function isFilePathSet($exe)
	{
		return(isset($this->filePath[$exe]) && !empty($this->filePath[$exe]));
	}
	
	private function setFilePath($exe)
	{
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

function findEXE( $exe )
{
	global $pathToExternals;
	if(isset($pathToExternals[$exe]) && !empty($pathToExternals[$exe]))
		return(is_executable($pathToExternals[$exe]) ? $pathToExternals[$exe] : false);
	
	global $do_diagnostic;
	return(WhichCache::load($do_diagnostic)->getFilePath($exe));
}
