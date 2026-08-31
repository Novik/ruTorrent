<?php

require_once( 'lfs.php' );
require_once( 'user.php' );

class FileUtil
{
	private static $profilePathInstance = null;

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

	private static function logFileStreamScheme( $path )
	{
		if( !is_string( $path ) ||
			!preg_match('/^([A-Za-z][A-Za-z0-9+.-]*):\/\//', $path, $matches) )
			return(null);
		return($matches[1]);
	}

	private static function fileUriFilesystemPath( $path )
	{
		$parts = parse_url($path);
		if( !is_array($parts) || !isset($parts['scheme']) ||
			(strcasecmp($parts['scheme'], 'file') != 0) || !isset($parts['path']) ||
			isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) ||
			isset($parts['query']) || isset($parts['fragment']) )
			return(null);
		$host = isset($parts['host']) ? $parts['host'] : '';
		if( ($host === '') || (strcasecmp($host, 'localhost') == 0) )
			return($parts['path']);
		return( (DIRECTORY_SEPARATOR == '\\') ?
			'\\\\'.$host.str_replace('/', '\\', $parts['path']) : null );
	}

	private static function isAbsoluteLogPath( $path )
	{
		if( !is_string( $path ) || ($path === '') )
			return(false);
		if( $path[0] == '/' )
			return(true);
		return( (DIRECTORY_SEPARATOR == '\\') &&
			(preg_match('/^[A-Za-z]:[\\\\\/]/', $path) || (strncmp($path, '\\\\', 2) == 0)) );
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

	public static function getProfilePath()
	{
		if (is_null(self::$profilePathInstance))
			self::$profilePathInstance = self::getProfilePathEx();

		return(self::$profilePathInstance);
	}

	public static function getProfilePathEx($user = null)
	{
		global $profilePath;
		$ret = self::fullpath(isset($profilePath) ? $profilePath : '../../share', dirname(__FILE__));
		if(is_null($user))
			$user = User::getUser();
		if($user!='')
		{
			$ret.=('/users/'.$user);
			if(!is_dir($ret))
				self::makeDirectory( array($ret,$ret.'/settings',$ret.'/torrents',$ret.'/tmp') );
		}
		return $ret;
	}

	public static function getSettingsPath()
	{
		return( self::getProfilePath().'/settings' );
	}

	public static function getSettingsPathEx($user = null)
	{
		return( self::getProfilePathEx($user).'/settings' );
	}

	public static function getUploadsPath()
	{
		return( self::getProfilePath().'/torrents' );
	}

	public static function getUploadsPathEx($user = null)
	{
		return( self::getProfilePathEx($user).'/torrents' );
	}

	public static function getPluginConf($plugin)
	{
		$ret = '';
		$conf = dirname(__FILE__).'/../../plugins/'.$plugin.'/conf.php';
		if(is_file($conf) && is_readable($conf))
			$ret.='require("'.$conf.'");';
		$local = dirname(__FILE__).'/../../plugins/'.$plugin.'/conf.local.php';
		if(is_file($local) && is_readable($local))
			$ret.='require("'.$local.'");';
		$user = User::getUser();
		if($user!='')
		{
			$conf = dirname(__FILE__).'/../../conf/users/'.$user.'/plugins/'.$plugin.'/conf.php';
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
			$conf = dirname(__FILE__).'/../../conf/users/'.$user.'/'.$name;
			if(is_file($conf) && is_readable($conf))
				return($conf);
		}
		return(false);
	}

	public static function getUniqueFilename($fname)
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
		global $overwriteUploadedTorrents;
		$fname = self::getUploadsPath()."/".$fname;
		return( $overwriteUploadedTorrents ? $fname : self::getUniqueFilename($fname));
	}

	public static function getTempDirectory()
	{
		global $tempDirectory;
		global $tempDirectory_init_done;
		if(!$tempDirectory_init_done)
		{
			if(empty($tempDirectory))
			{
				// Auto temp dir.
				// Do not try to create tmp dir in system.
				$directories = array();
				if(ini_get('upload_tmp_dir'))
					$directories[] = ini_get('upload_tmp_dir');
				if(function_exists('sys_get_temp_dir'))
					$directories[] = sys_get_temp_dir();
				$directories[] = '/tmp';
				$directories[] = '/var/tmp';
				foreach ($directories as $directory)
				{
					if(!is_dir($directory) || !@file_exists($directory.'/.'))
						continue;
					if(!is_readable($directory) || !is_writable($directory))
						continue;
					$tempDirectory = $directory;
					break;
				}
				// Fallback: create tmp dir inside rutorrent user profile.
				if(empty($tempDirectory))
				{
					$tempDirectory = self::getProfilePath().'/tmp';
					FileUtil::makeDirectory($tempDirectory);
				}
			}
			else
			{
				// User provided, create if not exist.
				FileUtil::makeDirectory($tempDirectory);
			}
			// Make sure that temp dir always have trail slash.
			$tempDirectory = self::addslash( $tempDirectory );
			$tempDirectory_init_done = true;
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
		{
			foreach($dirs as $dir)
			{
				(file_exists(self::addslash($dir).'.') && @chmod($dir,$perms)) || @mkdir($dir,$perms,true);
			}
		}
		else
		{
			(file_exists(self::addslash($dirs).'.') && @chmod($dirs,$perms)) || @mkdir($dirs,$perms,true);
		}
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
			is_dir($path) ? self::deleteDirectory($path) : unlink($path);
		}
		return(rmdir($dir));
	}

	public static function toLog( $str )
	{
		global $log_file, $profileMask;
		if( $log_file && strlen( $log_file ) > 0 )
		{
			$streamScheme = self::logFileStreamScheme( $log_file );
			$isFileStream = !is_null($streamScheme) && (strcasecmp($streamScheme, 'file') == 0);
			$isFilesystem = is_null($streamScheme) || $isFileStream;
			$filesystemPath = $isFileStream ? self::fileUriFilesystemPath($log_file) : $log_file;
			if( $isFilesystem && (is_null($filesystemPath) || !self::isAbsoluteLogPath( $filesystemPath )) )
			{
				trigger_error('$log_file must be an absolute path or stream URI; the log line was not written.', E_USER_WARNING);
				return;
			}
			$logTarget = $isFilesystem ? $filesystemPath : $log_file;
			$exists = $isFilesystem ? file_exists( $logTarget ) : @file_exists( $logTarget );
			// dmrom: set proper permissions (need if rtorrent user differs from www user)
			if( !$exists && $isFilesystem )
			{
				touch( $logTarget );
				chmod( $logTarget, (isset($profileMask) ? $profileMask : 0777) & 0666 );
			}
			// Regular and unstatable stream logs only need write access. Keep the
			// old mode for FIFOs, where a write-only open blocks until a reader appears.
			$w = fopen( $logTarget, (!$isFilesystem && !$exists) || is_file( $logTarget ) ? "ab" : "ab+" );
			if( $w )
			{
				fputs( $w, "[".date_create()->format('Y-m-d H:i:s')."] {$str}\n" );
				fclose( $w );
			}
		}
	}

	public static function getMinFilePerms( $file, $chmod = 0755 )
	{
		$code = fileperms($file);

		if($code!==false)
			return(($code & 0777) >= $chmod);

		return false;
	}
}
