<?php
require_once( 'util.php' );

class rCache
{
	protected $dir;

	public function __construct( $name = '' )
	{
		$this->dir = getSettingsPath().$name;
		if(!is_dir($this->dir))
			makeDirectory($this->dir);
	}
	public static function flock( $fp )
	{
		$i = 0;
		while(!flock($fp, LOCK_EX | LOCK_NB))
		{
			usleep(round(rand(0, 100)*1000));
			if(++$i>20)
				return(false);
		}
		return(true);
	}
	public function set( $rss, $arg = null )
	{
		global $profileMask;
		$name = $this->getName($rss);
		if(     is_object($rss) &&
			isset($rss->modified) &&
			method_exists($rss,"merge") &&
			($rss->modified < filemtime($name)))
		{
		        $className = get_class($rss);
			$newInstance = new $className();
			if($this->get($newInstance) &&
				!$rss->merge($newInstance, $arg))
				return(false);
		}
		$fp = fopen( $name.'.tmp', "a" );
		if($fp!==false)
		{
			if(self::flock( $fp ))
			{
				ftruncate( $fp, 0 );
				$str = serialize( $rss );
	        		if((fwrite( $fp, $str ) == strlen($str)) && fflush( $fp ))
	        		{
					flock( $fp, LOCK_UN );
        				if(fclose( $fp ) !== false)
        				{
	       					@rename( $name.'.tmp', $name );
						@chmod($name,$profileMask & 0666);
	        				return(true);
					}
					else
						unlink( $name.'.tmp' );
				}
				else
				{
					flock( $fp, LOCK_UN );
        				fclose( $fp );
        				unlink( $name.'.tmp' );
				}	        			
	        	}
	        	else
		        	fclose( $fp );
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
			if(is_array($tmp))
			{
			        $rss = $tmp;				
				$ret = true;
			}
			else
			{
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
        	}
		return($ret);
	}
	public function remove( $rss )
	{
		return(@unlink($this->getName($rss)));
	}
	protected function getName($rss)
	{
	        return($this->dir."/".(is_object($rss) ? $rss->hash : $rss['__hash__']));
	}
	public function getModified( $obj = null )
	{
		return(filemtime( is_null($obj) ? $this->dir : 
			(is_object($obj) ? $this->getName($obj) : $this->dir."/".$obj) ));
			
	}
}
