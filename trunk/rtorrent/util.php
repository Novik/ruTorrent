<?php

require_once( 'config.php' );
require_once( 'Torrent.php' );
//require_once( 'bencode.php' );
//require_once( 'class.bdecode.php' );

function quoteAndDeslashEachItem($item)
{
	return("'".addslashes($item)."'"); 
}

function isInvalidUTF8($s)
{
	return(preg_match( '/^([\x00-\x7f]|[\xc0-\xdf][\x80-\xbf]|' .
                '[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xf7][\x80-\xbf]{3})+$/', $s )!=1);

}

function win2utf($str) 
{
	$outstr='';
	$recode=array(
	0x0402,0x0403,0x201A,0x0453,0x201E,0x2026,0x2020,0x2021,
	0x20AC,0x2030,0x0409,0x2039,0x040A,0x040C,0x040B,0x040F,
	0x0452,0x2018,0x2019,0x201C,0x201D,0x2022,0x2013,0x2014,
	0x0000,0x2122,0x0459,0x203A,0x045A,0x045C,0x045B,0x045F,
	0x00A0,0x040E,0x045E,0x0408,0x00A4,0x0490,0x00A6,0x00A7,
	0x0401,0x00A9,0x0404,0x00AB,0x00AC,0x00AD,0x00AE,0x0407,
	0x00B0,0x00B1,0x0406,0x0456,0x0491,0x00B5,0x00B6,0x00B7,
	0x0451,0x2116,0x0454,0x00BB,0x0458,0x0405,0x0455,0x0457,
	0x0410,0x0411,0x0412,0x0413,0x0414,0x0415,0x0416,0x0417,
	0x0418,0x0419,0x041A,0x041B,0x041C,0x041D,0x041E,0x041F,
	0x0420,0x0421,0x0422,0x0423,0x0424,0x0425,0x0426,0x0427,
	0x0428,0x0429,0x042A,0x042B,0x042C,0x042D,0x042E,0x042F,
	0x0430,0x0431,0x0432,0x0433,0x0434,0x0435,0x0436,0x0437,
	0x0438,0x0439,0x043A,0x043B,0x043C,0x043D,0x043E,0x043F,
	0x0440,0x0441,0x0442,0x0443,0x0444,0x0445,0x0446,0x0447,
	0x0448,0x0449,0x044A,0x044B,0x044C,0x044D,0x044E,0x044F
	);
	$and=0x3F;
	for ($i=0;$i<strlen($str);$i++) {
	    $octet=array();
	    if (ord($str[$i])<0x80) {
		$strhex=ord($str[$i]);
	    } else {
		$strhex=$recode[ord($str[$i])-128];
	    }
	    if ($strhex<0x0080) {
		$octet[0]=0x0;
	    } elseif ($strhex<0x0800) {
		$octet[0]=0xC0;
		$octet[1]=0x80;
	    } elseif ($strhex<0x10000) {
		$octet[0]=0xE0;
		$octet[1]=0x80;
		$octet[2]=0x80;
	    } elseif ($strhex<0x200000) {
		$octet[0]=0xF0;
		$octet[1]=0x80;
		$octet[2]=0x80;
		$octet[3]=0x80;
	    } elseif ($strhex<0x4000000) {
		$octet[0]=0xF8;
		$octet[1]=0x80;
		$octet[2]=0x80;
		$octet[3]=0x80;
		$octet[4]=0x80;
	    } else {
		$octet[0]=0xFC;
		$octet[1]=0x80;
		$octet[2]=0x80;
		$octet[3]=0x80;
		$octet[4]=0x80;
		$octet[5]=0x80;
	    }
	    for ($j=(count($octet)-1);$j>=1;$j--) {
		$octet[$j]=$octet[$j] + ($strhex & $and);
		$strhex=$strhex>>6;
	    }
	    $octet[0]=$octet[0] + $strhex;
	    for ($j=0;$j<count($octet);$j++) {
		$outstr.=chr($octet[$j]);
	    }
	}
	return($outstr);
}

function toLog( $str )
{
//	$filename = "/opt/share/www/rtorrent/error.log";
	$filename = "/srv/www/htdocs/rtorrent/error.log";
	$w = fopen($filename, "ab+");
	if($w)
	{
		fputs($w,"[".strftime("%d.%m.%y %H:%M:%S")."] {$str}\n");
		fclose($w);
	}
}

function send2RPC( $data )
{
//toLog($data);
	global $scgi_host;
	global $scgi_port;
	$result = "";
	$contentlength = strlen($data);
	if($contentlength>0)
	{
		$socket = @fsockopen($scgi_host, $scgi_port, $errno, $errstr, RPC_TIME_OUT);
		if($socket) 
		{
			$reqheader =  "CONTENT_LENGTH\x0".$contentlength."\x0"."SCGI\x0"."1\x0";
			$tosend = strlen($reqheader).":{$reqheader},{$data}";
			@fputs($socket,$tosend);
			while (!feof($socket)) 
			{
				$result .= @fread($socket, 4096);
			}
			fclose($socket);
		}
	}
//toLog($result);
	return($result);
}

function isUserHavePermissionPrim($uid,$gid,$file,$flags)
{
	if($gid==0)
		return(true);
	$ss=@stat($file);
	if($ss)
	{
		$p=$ss['mode'];
		if(($p & $flags) == $flags)
		{
			return(true);
		}
		$flags<<=3;
        	if(($gid==$ss['gid']) &&
			(($p & $flags) == $flags))
			return(true);
		$flags<<=3;
		if(($uid==$ss['uid']) &&
			(($p & $flags) == $flags))
			return(true);
	}
	return(false);
}

function isUserHavePermission($uid,$gid,$file,$flags)
{
	if($gid==0)
		return(true);
	if(is_link($file))
		$file = readlink($file);
	if(isUserHavePermissionPrim($uid,$gid,$file,$flags))
	{
		if(($flags & 0x0002) && !is_dir($file))
			$flags = 0x0007;
		else
			$flags = 0x0005;
		return(isUserHavePermissionPrim($uid,$gid,dirname($file),$flags));
	}
	return(false);
}

function sendFile2rTorrent($fname, $isURL, $isStart, $isAddPath, $directory, $label, $addition = '')
{
	$hash = false;
	if($isStart)
		$method = 'load_start_verbose';
	else
		$method = 'load_verbose';
	$comment = "";	
	$delete_tied = "";
	if(!$isURL)
	{
		$torrent = new Torrent($fname);
		if($torrent->errors())
			return(false);
		$comment = $torrent->comment();

		if($comment)
		{
			if(isInvalidUTF8($comment))
				$comment = win2utf($comment);
			if(strlen($comment)>0)
				$comment = "<param><value><string>d.set_custom2=VRS24mrker".rawurlencode($comment)."</string></value></param>";
		}
		else
			$comment = "";
		$hash = $torrent->hash_info();
		$delete_tied = '<param><value><string>d.delete_tied=</string></value></param>';
	}
	$setlabel = "";
	if($label && (strlen($label)>0))
		$setlabel = "<param><value><string>d.set_custom1=\"".rawurlencode($label)."\"</string></value></param>";
	$setdir = "";
	if($directory && (strlen($directory)>0))
	{
		if(!$isAddPath)
			$setdir = "d.set_directory_base=\"";
		else
			$setdir = "d.set_directory=\"";
		$setdir = "<param><value><string>".$setdir.$directory."\"</string></value></param>";
	}
	$content = 
		'<?xml version="1.0" encoding="UTF-8"?>'.
		'<methodCall>'.
		'<methodName>'.$method.'</methodName>'.
		'<params>'.
		'<param><value><string>'.$fname.'</string></value></param>'.
		$setdir.
		$comment.
		$setlabel.
		$addition.
		$delete_tied.
		'</params></methodCall>';
	$result = send2RPC($content);
	if($result='')
		$hash = false;
	return($hash);
}

function findEXE( $exe )
{
	$path = explode(":", getenv('PATH'));
	foreach($path as $tryThis)
	{
		$fname = $tryThis . '/' . $exe;
		if(is_executable($fname))
			return($fname);
	}
	return(false);
}

function findRemoteEXE( $exe, $err, &$remoteRequests )
{
	global $settings;
	$st=realpath($settings);
	$len = strlen($st);
	if(($len>0) && ($st[$len-1]!='/'))
		$st.='/';
	if(!is_file($st.$exe))
	{
		if(!array_key_exists($exe,$remoteRequests))
		{
			$path=realpath(dirname('.'));
			$len = strlen($path);
			if(($len>0) && ($path[$len-1]!='/'))
				$path.='/';
			send2RPC(
				"<?xml version=\"1.0\" encoding=\"UTF-8\"?>".
				"<methodCall>".
				"<methodName>execute</methodName>".
				"<params>".
				"<param><value><string>sh</string></value></param>".
				"<param><value><string>-c</string></value></param>".
				"<param><value><string>".$path."test.sh ".$exe." ".$st."</string></value></param>".
				"</params>".
				"</methodCall>");
		}
		$remoteRequests[$exe][] = $err;
	}
}

function testRemoteRequests($remoteRequests)
{
	$jResult = "";
	global $settings;
	foreach($remoteRequests as $exe=>$errs)
	{
		$file = $settings."/".$exe.".founded";
		if(!is_file($file))
		{
			foreach($errs as $err)
				$jResult.=$err;
		}
		else
			@unlink($file);
	}
	return($jResult);
}

class rCache
{
	protected $dir;

	public function rCache( $dir = "./cache" )
	{
		$this->dir = $dir;
		if(!is_dir($this->dir))
			mkdir($this->dir, 0777);
	}
	public function set( $rss )
	{
		$name = $this->getName($rss);
		$fp = @fopen( $name, 'w' );
		if($fp)
		{
		        fwrite( $fp, serialize( $rss ) );
        		fclose( $fp );
			chmod($name,0777);
	        	return(true);
        	}
	        return(false);
	}
	public function get( &$rss )
	{
		$ret = @file_get_contents($this->getName($rss));
		if($ret!==false)
		{
			$rss = unserialize($ret);
			$ret = true;
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
}
?>
