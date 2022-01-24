<?php

@define("MIN_CACHE",	8);
@define("MAX_CACHE", 	10);
@define("SIZEOF_HASH", 	40);
@define("SIZEOF_MD5", 	32);

require_once( dirname(__FILE__)."/../../php/util.php" );

function array_diff_assoc_recursive($array1, $array2)
{
	foreach($array1 as $key => $value)
	{
		if(is_array($value))
		{
			if(!isset($array2[$key]))
				$difference[$key] = $value;
			elseif(!is_array($array2[$key]))
				$difference[$key] = $value;
			else
			{
				$diff = array_diff_assoc_recursive($value, $array2[$key]);
				if($diff!==false)
					$difference[$key] = $diff;
			}
		}
		elseif(!isset($array2[$key]) || $array2[$key] != $value)
			$difference[$key] = $value;
	}
	return(isset($difference) ? $difference : false);
} 

class rpcCache
{

	protected $dir;
        
        public function __construct()
        {
		$this->dir = FileUtil::getSettingsPath()."/httprpc";
		if(!is_dir($this->dir))
			FileUtil::makeDirectory($this->dir);
        }
	
	protected function store( $torrents = array() )
	{
	        $cid = 0;
		$result = serialize($torrents);
		if($result!==false)
		{
			$cid = crc32($result);
			if(file_put_contents($this->dir.'/'.dechex($cid),$result)!=strlen($result))
				@unlink($this->dir.'/'.dechex($cid));
		}
		$this->strip();
		return($cid);
	}

	protected function load( $cid )
	{
		$torrents = array();
		if($cid)
		{
			$ret = @file_get_contents($this->dir.'/'.dechex($cid));
			if($ret!==false)
			{
				$torrents = unserialize($ret);
				if($torrents===false)
					$torrents = array();
			}
		}
		return($torrents);
	}

	protected function strip()
	{
		if($dh = opendir($this->dir)) 
		{
			$files = array();
		        while(($file = readdir($dh)) !== false) 
		        {
				$filename = $this->dir.'/'.$file;
		        	if(is_file($filename))
		        	         $files[$filename] = filemtime($filename);
		        }
		        closedir($dh);
		        if(count($files)>MAX_CACHE)
		        {
		        	asort($files,SORT_NUMERIC);
		        	$i = 0;
		        	foreach( $files as $file=>$time )
		        	{	
					@unlink( $file );
					$i++;
					if($i>(MAX_CACHE-MIN_CACHE))
						break;
				}
			}
		}
	}

	public function calcDifference( &$cid, &$torrents, &$dTorrents )
	{
		$oldTorrents = $this->load( $cid );
		$cid = $this->store( $torrents );
		$mod = array_diff_assoc_recursive($torrents,$oldTorrents);
		$del = array_diff_key($oldTorrents,$torrents);
		foreach($del as $hash=>$val)
			$dTorrents[] = $hash;
		$torrents = ($mod===false) ? array() : $mod;
		return(count($oldTorrents)>0);
	}

}
