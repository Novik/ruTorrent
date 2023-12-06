<?php

require_once( 'lfs.php' );

class SendFile
{
	// Copy this function from FileUtil.php
	// We don't need the whole class for one function
	private static function getFileName($path)
	{
		$arr = explode('/',$path);
		return(end($arr));
	}
	
	public static function send( $filename, $contentType = null, $nameToSent = null, $mustExit = true )
	{
		global $canUseXSendFile;
		$stat = @LFS::stat($filename);
		if($stat && @LFS::is_file($filename) && @LFS::is_readable($filename))
		{
			$etag = sprintf('"%x-%x-%x"', $stat['ino'], $stat['size'], $stat['mtime'] * 1000000);
			if( 	(isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag) ||
							(isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $stat['mtime']))
				header('HTTP/1.0 304 Not Modified');
			else
			{
				header('Content-Type: '.(is_null($contentType) ? 'application/octet-stream' : $contentType));
				if(is_null($nameToSent))
					$nameToSent = self::getFileName($filename);
				if(isset($_SERVER['HTTP_USER_AGENT']) && strstr($_SERVER['HTTP_USER_AGENT'],'MSIE'))
					$nameToSent = rawurlencode($nameToSent);
				header('Content-Disposition: attachment; filename="'.$nameToSent.'"');
		
				if( $mustExit && $canUseXSendFile &&
					function_exists('apache_get_modules') && 
					in_array('mod_xsendfile', apache_get_modules()))
				{ 
					header("X-Sendfile: ".$filename); 
				}
				else
				{
					header('Cache-Control: ');
					header('Expires: ');
					header('Pragma: ');
					header('Etag: '.$etag);
					header('Last-Modified: ' . date('r', $stat['mtime']));
					set_time_limit(0);
					ignore_user_abort(!$mustExit);
					header('Accept-Ranges: bytes');
					header('Content-Transfer-Encoding: binary');
					header('Content-Description: File Transfer');

					if(ob_get_level()) 
						while(@ob_end_clean());

					$begin = 0;
					$end = $stat['size'];
					if(isset($_SERVER['HTTP_RANGE']))
					{ 
						if(preg_match('/bytes=\h*(\d+)-(\d*)[\D.*]?/i', $_SERVER['HTTP_RANGE'], $matches))
							{ 
								$begin=intval($matches[1]);
							if(!empty($matches[2]))
								$end=intval($matches[2]);
						}
					}
					$size = $end - $begin;
					if((PHP_INT_SIZE<=4) && ($size >= 2147483647))
						passthru('cat '.escapeshellarg($filename));
					else
					{
						if(!ini_get("zlib.output_compression"))
							header('Content-Length:' . $size);
						if($size != $stat['size'])
						{
							$f = @fopen($filename,'rb');
							if($f===false)
								header ("HTTP/1.0 505 Internal Server Error");
							else
							{
								header('HTTP/1.0 206 Partial Content');
								header("Content-Range: bytes ".$begin."-".$end."/".$stat['size']);
								$cur = $begin;
								fseek($f,$begin,0);
								while( !feof($f) && ($cur<$end) && !connection_aborted() && (connection_status()==0) )
								{ 
									print(fread($f,min(1024*16,$end-$cur)));
									$cur+=1024*16;
								}
								fclose($f);
							}
						}
						else
						{
							header('HTTP/1.0 200 OK');  
							readfile($filename);
						}
					}
				}
			}
			if($mustExit)
				exit(0);
			else
				return(true);
		}
		return(false);
	}
	
	public static function sendCachedImage($location, $type, $duration)
	{
		header('Content-Type: '.$type);
		header('Cache-Control: max-age='.$duration);
		header('HTTP/1.0 200 OK');
		readfile($location);
		exit;
	}
}