<?php

require_once( 'lfs.php' );
require_once( 'user.php' );

class FileUtil extends ruTorrentConfig
{
	public static function getFileName($path)
	{
		$arr = explode('/',$path);
		return(end($arr));
	}
	
	public static function addslash( $str )
	{
		$len = strlen( $str );
		return( (($len == 0) || ($str[$len-1] == '/')) ? $str : $str.'/' );
	}
	
	public static function delslash( $str )
	{
		$len = strlen( $str );
		return( (($len == 0) || ($str[$len-1] != '/')) ? $str : substr($str,0,$len-1) );
	}
	
	public static function fullpath($path,$base = '')
	{
		$root  = '';
		if(strlen($path) && ($path[0] == '/'))
				$root = '/';
		else
			return(self::fullpath(self::addslash($base).$path,getcwd()));
		$path=explode('/', $path);
		$newpath=array();
		foreach($path as $p)
		{
			if ($p === '' || $p === '.') continue;
			if ($p==='..')
				array_pop($newpath);
			else
				array_push($newpath, $p);
		}
		return($root.implode('/', $newpath));
	}
	
	public static function getProfilePath( $user = null )
	{
		$ret = self::fullpath(!is_null(self::profilePath) ? self::profilePath : '../share', dirname(__FILE__));
		if(is_null($user))
			$user = User::getUser();
		if($user!='')
		{
			$ret.=('/users/'.$user);
			if(!is_dir($ret))
				self::makeDirectory( array($ret,$ret.'/settings',$ret.'/torrents',$ret.'/tmp') );
		}
		return($ret);
	}
	
	public static function getSettingsPath( $user = null )
	{
		return( self::getProfilePath($user).'/settings' );
	}

	public static function getUploadsPath( $user = null )
	{
		return( self::getProfilePath($user).'/torrents' );
	}
	
	public static function getPluginConf($plugin)
	{
		$ret = '';
		global $rootPath;
		$conf = $rootPath.'/plugins/'.$plugin.'/conf.php';
		if(is_file($conf) && is_readable($conf))
			$ret.='require("'.$conf.'");';
		$user = User::getUser();
		if($user!='')
		{
			$conf = $rootPath.'/conf/users/'.$user.'/plugins/'.$plugin.'/conf.php';
			if(is_file($conf) && is_readable($conf))
				$ret.='require("'.$conf.'");';
		}
		return($ret);
	}
	
	public static function getConfFile($name)
	{
		$user = User::getUser();
		if($user!='')
		{
			global $rootPath;
			$conf = $rootPath.'/conf/users/'.$user.'/'.$name;
			if(is_file($conf) && is_readable($conf))
				return($conf);
		}
		return(false);
	}
	
	private static function getUniqueFilename($fname)
	{
		while(file_exists($fname))
		{
			$ext = '';
			$pos = strrpos($fname,'.');
			if($pos!==false) 
			{
				$ext = substr($fname,$pos);
				$fname = substr($fname,0,$pos);
			}
			$pos = preg_match('/.*\((?P<no>\d+)\)$/',$fname,$matches);
			$no = 1;
			if($pos)
			{		
				$no = intval($matches["no"])+1;
				$fname = substr($fname,0,strrpos($fname,'('));
			}
			$fname = $fname.'('.$no.')'.$ext;
		}
		return($fname);
	}

	public static function getUniqueUploadedFilename($fname)
	{
		$fname = self::getUploadsPath()."/".$fname;
		return( self::overwriteUploadedTorrents ? $fname : self::getUniqueFilename($fname));
	}
	
	public static function getTempDirectory() 
	{
		global $tempDirectory;		
		if(empty($tempDirectory))
		{
			$directories = array();
			if(ini_get('upload_tmp_dir')) 
				$directories[] = ini_get('upload_tmp_dir');
			if(function_exists('sys_get_temp_dir'))
				$directories[] = sys_get_temp_dir();
			$directories[] = '/tmp';
			foreach ($directories as $directory) 
			{
				if(is_dir($directory) && is_writable($directory)) 
				{
					$tempDirectory = $directory;
					break;
				}
			}
			if(empty($tempDirectory))
				$tempDirectory = self::getProfilePath().'/tmp';
			
			$tempDirectory = self::addslash( $tempDirectory );
		}
		return($tempDirectory);
	}
	
	public static function getTempFilename($purpose = '', $extension = null)
	{
		do
		{
			$fname = uniqid(self::getTempDirectory().implode( '-', array_filter(array
			(
				"rutorrent",
				$purpose,
				User::getLogin(),
				getmypid()
			))),true).( is_null($extension) ? '' : ".$extension" );
		} while(file_exists($fname));	// this is no guarantee, of course...
		return($fname);
	}
	
	public static function makeDirectory( $dirs, $perms = null )
	{
		global $profileMask;
		if(is_null($perms))
			$perms = isset($profileMask) ? $profileMask : 0777;
		$oldMask = umask(0);
		if(is_array($dirs))
			foreach($dirs as $dir)
				(file_exists(self::addslash($dir).'.') && @chmod($dir,$perms)) || @mkdir($dir,$perms,true);
		else
			(file_exists(self::addslash($dirs).'.') && @chmod($dirs,$perms)) || @mkdir($dirs,$perms,true);
		@umask($oldMask);
	} 

	// [fixme] hidden files doesn't processed
	public static function deleteDirectory( $dir )
	{
		$dir = self::addslash($dir);
		$files = array_diff(scandir($dir), array('.','..'));
		foreach($files as $file) 
		{
			$path = $dir.$file;
			is_dir($path) ? deleteDirectory($path) : unlink($path);
		}
		return(rmdir($dir));
	}
	
	public static function toLog( $str )
	{		
		if( self::log_file && strlen( self::log_file ) > 0 )
		{
			// dmrom: set proper permissions (need if rtorrent user differs from www user)
			if( !is_file( self::log_file ) )
			{
				touch( self::log_file );
				chmod( self::log_file, 0666 );
			}
			$w = fopen( self::log_file, "ab+" );
			if( $w )
			{
				fputs( $w, "[".strftime( "%d.%m.%y %H:%M:%S" )."] {$str}\n" );
				fclose( $w );
			}
		}
	}
}