<?php

require_once( 'fileutil.php' );
require_once( 'utility.php' );

class CachedEcho
{	
	public static function send( $content, $type = null, $cacheable = false, $exit = true )
	{
		header("X-Server-Timestamp: ".time());
		if($cacheable && isset($_SERVER['REQUEST_METHOD']) && ($_SERVER['REQUEST_METHOD']=='GET'))
		{
			$etag = '"'.strtoupper(dechex(crc32($content))).'"';
			header('Expires: ');
			header('Pragma: ');
			header('Cache-Control: ');
			if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag)
			{
				header('HTTP/1.0 304 Not Modified');
				return;
			}
			header('Etag: '.$etag);
		}
		if(!is_null($type))
			header("Content-Type: ".$type."; charset=UTF-8");
		$len = strlen($content);
		if(ini_get("zlib.output_compression") && ($len<2048))
			ini_set("zlib.output_compression",false);
		if(!ini_get("zlib.output_compression"))
		{
				if(PHP_USE_GZIP && isset($_SERVER['HTTP_ACCEPT_ENCODING']))
				{
					if( strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'x-gzip') !== false ) 
						$encoding = 'x-gzip'; 
				else if( strpos($_SERVER['HTTP_ACCEPT_ENCODING'],'gzip') !== false )
						$encoding = 'gzip'; 
				if($encoding && ($len>=2048))
				{
					$gzip = Utility::getExternal('gzip');
					header('Content-Encoding: '.$encoding); 
					$randName = FileUtil::getTempFilename('answer');
					file_put_contents($randName,$content);
					passthru( $gzip." -".PHP_GZIP_LEVEL." -c < ".$randName );
					unlink($randName);
					return;
				}
			}
			header("Content-Length: ".$len);
		}
		if($exit)
			exit($content);
		else
			echo($content);
	}
}