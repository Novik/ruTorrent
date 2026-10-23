<?php

require_once( 'fileutil.php' );
require_once( 'utility.php' );

class CachedEcho
{
	private static function gzipEncoding($header)
	{
		$weights = array();
		foreach(explode(',', $header) as $item)
		{
			$parts = explode(';', trim($item), 2);
			$name = strtolower(trim($parts[0]));
			if(!in_array($name, array('gzip', 'x-gzip', '*'), true))
				continue;
			$weight = 1.0;
			if(isset($parts[1]))
			{
				if(preg_match('/^q\s*=\s*(0(?:\.[0-9]{0,3})?|1(?:\.0{0,3})?)\s*$/iD',
					trim($parts[1]), $match))
					$weight = (float)$match[1];
				else
					$weight = 0.0;
			}
			// Repeated codings retain their lowest weight, including explicit refusals.
			$weights[$name] = isset($weights[$name]) ? min($weights[$name], $weight) : $weight;
		}
		$gzip = $weights['gzip'] ?? 0.0;
		$xGzip = $weights['x-gzip'] ?? 0.0;
		// x-gzip aliases gzip; a wildcard cannot undo an explicit refusal of either label.
		if(!isset($weights['gzip']) && !isset($weights['x-gzip']))
			$gzip = $weights['*'] ?? 0.0;
		if(($gzip > 0) && ($gzip >= $xGzip)) return 'gzip';
		return ($xGzip > 0) ? 'x-gzip' : null;
	}

	public static function send( $content, $type = null, $cacheable = false, $exit = true )
	{
		header("X-Server-Timestamp: ".time());
		// Every response this class sends declares its type below, so a
		// browser has no reason to guess one. Without this a response the
		// caller sent as json or as a download can still be sniffed into
		// html and rendered.
		header("X-Content-Type-Options: nosniff");
		if($cacheable && isset($_SERVER['REQUEST_METHOD']) && ($_SERVER['REQUEST_METHOD']=='GET'))
		{
			$etag = '"'.strtoupper(dechex(crc32($content))).'"';
			header('Expires: ');
			header('Pragma: ');
			header('Cache-Control: ');
			if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag)
			{
				header('HTTP/1.0 304 Not Modified');
				if($exit) exit;
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
				global $phpUseGzip;
				if($phpUseGzip && isset($_SERVER['HTTP_ACCEPT_ENCODING']))
				{
					$encoding = self::gzipEncoding($_SERVER['HTTP_ACCEPT_ENCODING']);
				if($encoding && ($len>=2048))
				{
					global $phpGzipLevel;
					$gzip = Utility::getExternal('gzip');
					header('Content-Encoding: '.$encoding);
					$randName = FileUtil::getTempFilename('answer');
					file_put_contents($randName,$content);
					passthru( $gzip." -".$phpGzipLevel." -c < ".$randName );
					unlink($randName);
					if($exit) exit;
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
