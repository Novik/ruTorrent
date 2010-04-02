<?php

define("MAX_CACHE", 	16);
define("SIZEOF_HASH", 	40);
define("SIZEOF_MD5", 	32);

require_once( dirname(__FILE__)."/../../php/util.php" );

class rpcCache
{

	protected $dir;
        
        public function rpcCache()
        {
		$this->dir = getSettingsPath()."/httprpc";
		if(!is_dir($this->dir))
			mkdir($this->dir, 0777);
        }
	
	protected function makeHash( $torrents = array() )
	{
		$torrentsHash = array();
		foreach($torrents as $torrent)
		{
			$hash = '';
			foreach($torrent as $var)
				$hash.=$var;
			$torrentsHash[$torrent[0]] = md5($hash);
		}
		return($torrentsHash);
	}

	protected function storeHash( $torrentsHash = array() )
	{
		$cid = time();
		$w = fopen($this->dir.'/'.$cid, "wb");
		foreach($torrentsHash as $key=>$data)
		{
			fputs($w,$key);
			fputs($w,$data);
		}
		fclose($w);
		$this->strip();
		return($cid);
	}

	protected function loadHash( $cid )
	{
		$torrentsHash = array();
		if($cid)
		{
			$filename = $this->dir.'/'.$cid;
			if( is_readable($filename) && is_file($filename) )
			{
				$w = fopen($filename, "rb");
				while(!feof($w))
				{
					$hash = fgets($w,SIZEOF_HASH+1);
					if($hash!=false)
						$torrentsHash[$hash] = fgets($w,SIZEOF_MD5+1);
	    			}
				fclose($w);
			}
		}
		return($torrentsHash);
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
					if($i>MAX_CACHE/2)
						break;
				}
			}
		}
	}

	public function calcDifference( $torrents, &$cid, &$mTorrents, &$dTorrents )
	{
		$torrentsHash = $this->makeHash( $torrents );
		$oldHash = $this->loadHash( $cid );
		$cid = $this->storeHash( $torrentsHash );
		$mod = array_diff_assoc($torrentsHash,$oldHash);
		foreach($torrents as $torrent)
		{
			if(array_key_exists($torrent[0],$mod))
				$mTorrents[] = $torrent;
		}
		$del = array_diff_key($oldHash,$torrentsHash);
		foreach($del as $hash=>$val)
			$dTorrents[] = $hash;
		return(count($oldHash)>0);
	}

}

?>