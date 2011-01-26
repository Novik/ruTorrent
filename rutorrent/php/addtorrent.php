<?php

require_once( 'Snoopy.class.inc');
require_once( 'rtorrent.php' );

set_time_limit(0);

$uploaded_file = '';
$success = false;

if(isset($_REQUEST['result']))
	cachedEcho('log(theUILang.addTorrent'. ($_REQUEST['result'] ? 'Success' : 'Failed') . ');',"text/html");
$label = null;
if(isset($_REQUEST['label']))	
	$label = trim($_REQUEST['label']);
$dir_edit = null;
if(isset($_REQUEST['dir_edit']))	
	$dir_edit = trim($_REQUEST['dir_edit']);
if(isset($_FILES['torrent_file']))
{
	$uploaded_file = getUploadsPath().'/'.$_FILES['torrent_file']['name'];
	if(pathinfo($uploaded_file,PATHINFO_EXTENSION)!="torrent")
		$uploaded_file.=".torrent";
	$uploaded_file = getUniqueFilename($uploaded_file);
	$success = move_uploaded_file($_FILES['torrent_file']['tmp_name'],$uploaded_file);
}
else
	if(isset($_REQUEST['url']))
	{
		$url = trim($_REQUEST['url']);
		if(strpos($url,"magnet:")===0)
		{
			$success = rTorrent::sendMagnet($url,
				!isset($_REQUEST['torrents_start_stopped']),
				!isset($_REQUEST['not_add_path']),
				$dir_edit,$label);
			header("HTTP/1.0 302 Moved Temporarily");
			header("Location: ".$_SERVER['PHP_SELF'].'?result='.intval($success));
			exit();
		}
		else
		{
			$cli = new Snoopy();
			$cli->agent = HTTP_USER_AGENT;
			$cli->read_timeout = HTTP_TIME_OUT;
			$client->_fp_timeout = HTTP_TIME_OUT;
			$cli->use_gzip = HTTP_USE_GZIP;
			if(@$cli->fetchComplex(Snoopy::linkencode($url)) && $cli->status>=200 && $cli->status<300)
			{
			        $name = $cli->get_filename();
			        if($name===false)
					$name = md5($url).".torrent";
				$uploaded_file = getUniqueFilename(getUploadsPath()."/".$name);
				$f = @fopen($uploaded_file,"w");
				if($f!==false)
				{
					@fwrite($f,$cli->results,strlen($cli->results));
					fclose($f);
					$success = true;
				}
			}
		}
	}
if($success)
{
	@chmod($uploaded_file,0666);
	$uploaded_file = realpath($uploaded_file);
	if(rTorrent::sendTorrent($uploaded_file,
		!isset($_REQUEST['torrents_start_stopped']),
		!isset($_REQUEST['not_add_path']),
		$dir_edit,$label,$saveUploadedTorrents,isset($_REQUEST['fast_resume']))===false)
	{
                unlink($uploaded_file);
                $success = false;
	}
}
header("HTTP/1.0 302 Moved Temporarily");
header("Location: ".$_SERVER['PHP_SELF'].'?result='.intval($success));
?>