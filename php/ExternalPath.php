<?php

require_once( dirname(__FILE__)."/../conf/config.php");
require_once( "cache.php" );

class ExternalPath extends RTorrentConfig {
	
	public $hash = "ExternalPath.dat";
	
	public static function load()
	{
		$cache = new rCache();
		$external = new ExternalPath();
		if(!$cache->get($external))
		{
			$external->updateExternalPaths();
			$cache->set($external);			
		}			
		return($external);
	}
	
	public function store()
	{
		$cache = new rCache();
		return $cache->set($this);	
	}
	
	private function updateExternalPaths()
	{
		foreach ($this->pathToExternals as $key => $value)
		{
			if (!$this->isExternalPathSet($key))
			{
				$this->pathToExternals[$key] = $this->findExternalPath($key);
			}
		}
	}

	// Returns if the external path to the program is set
	public function isExternalPathSet($exe)
	{
		return(isset($this->pathToExternals[$exe]) && !empty($this->pathToExternals[$exe]));
	}
	
	// Returns the external path to the program. 
	public function getExternalPath($exe)
	{
		return($this->pathToExternals[$exe]);
	}
	
	// Returns external path to the program if set. If not set, returns name of program
	public function getExternalPathEx($exe)
	{
		return($this->isExternalPathSet($exe) ? $this->pathToExternals[$exe] : $exe);
	}
	
	// Wrapper which returns etheir the path to php or the word php
	public function getPHP()
	{
		return($this->getExternalPathEx("php"));
	}
	
	// Long way of fetching an external path. Useful for internal lookups
	public function findExternalPath( $exe )
	{
		$result = $this->performPathLookup($exe);
		return $result === false ? '' : $result;
	}
	
	// Long way of fetching an external path. Useful for resolving errors
	public function fetchExternalPath($exe)
	{
		// If path is already in array, just return it
		if ($this->isExternalPathSet($exe))
			return $this->getExternalPath($exe);
		
		return $this->performPathLookup($exe);
	}
	
	private function performPathLookup($exe)
	{
		// Try the 'which' shell command first. Check for a valid output
		// The 'PATH' environment method may not work properly
		// The 'which' command also has performance advantages
		$cmd = shell_exec('which '.$exe);
		if (isset($cmd) && !empty($cmd))
		{
			// Trim string, update array, update cache, return value
			$loc = trim($cmd);
			$this->pathToExternals[$exe] = $loc;
			$this->store();
			return $loc;			
		}
		
		// Otherwise look through all the environment paths
		$path = explode(":", getenv('PATH'));
		foreach($path as $tryThis)
		{
			// Check if the file is found in the folder
			$fname = $tryThis . '/' . $exe;
			if(is_executable($fname))
			{
				// Update array, update cache, return value
				$this->pathToExternals[$exe] = $fname;
				$this->store();
				return($fname);
			}
		}
		return false; 		
	}
}
