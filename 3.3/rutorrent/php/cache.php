<?php
require_once( 'util.php' );

class rCache
{
	protected $dir;

	public function rCache( $name = '' )
	{
		$this->dir = getSettingsPath().$name;
		if(!is_dir($this->dir))
			makeDirectory($this->dir);
	}
	public function set( $rss, $arg = null )
	{
		global $profileMask;
		$name = $this->getName($rss);
		if(isset($rss->modified) &&
			method_exists($rss,"merge") &&
			($rss->modified < filemtime($name)))
		{
		        $className = get_class($rss);
			$newInstance = new $className();
			if($this->get($newInstance) &&
				!$rss->merge($newInstance, $arg))
				return(false);
		}
		$fp = @fopen( $name, 'w' );
		if($fp)
		{
		        fwrite( $fp, serialize( $rss ) );
        		fclose( $fp );
			@chmod($name,$profileMask & 0666);
	        	return(true);
        	}
	        return(false);
	}
	public function get( &$rss )
	{
	        $fname = $this->getName($rss);
		$ret = @file_get_contents($fname);
		if($ret!==false)
		{
			$tmp = unserialize($ret);
			if(($tmp!==false) && 
				(!isset($rss->version) || 
				(isset($rss->version) && !isset($tmp->version)) ||
				(isset($tmp->version) && ($tmp->version==$rss->version))))
			{
			        $rss = $tmp;
				$rss->modified = filemtime($fname);
				$ret = true;
			}
			else
				$ret = false;
        	}
		return($ret);
	}
	public function remove( $rss )
	{
		return(@unlink($this->getName($rss)));
	}
	protected function getName($rss)
	{
	        return($this->dir."/".$rss->hash);
	}
	public function getModified( $obj = null )
	{
		return(filemtime( is_null($obj) ? $this->dir : 
			(is_object($obj) ? $this->getName($obj) : $this->dir."/".$obj) ));
			
	}
}

?>