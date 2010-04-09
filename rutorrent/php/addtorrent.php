<?php

require_once( 'Snoopy.class.inc');
require_once( 'rtorrent.php' );

$uploaded_file = '';
$success = false;

if(isset($_REQUEST['result']))
{
	cachedEcho('log(theUILang.addTorrent'. ($_REQUEST['result'] ? 'Success' : 'Failed') . ');',"text/html");
	exit();
}
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
			header("Location: ".$_SERVER['PHP_SELF'].'?result='.intval($success));
			exit();
		}
		else
		{
			$cli = new Snoopy();
			$cli->agent = HTTP_USER_AGENT;
			$cli->read_timeout = HTTP_TIME_OUT;
			$cli->use_gzip = HTTP_USE_GZIP;
			$pos = strpos($url,':COOKIE:');
			if($pos!==false)
			{
				$tmp = explode(";",substr($url,$pos+8));
				foreach($tmp as $item)
				{
					list($name,$val) = explode("=",$item);
					$cli->cookies[$name] = $val;
				}
				$url = substr($url,0,$pos);
			}
			set_time_limit(0);
			if(@$cli->fetch($url) && $cli->status>=200 && $cli->status<300)
			{
			        $name = $cli->get_filename();
			        if($name===false)
					$name = md5($url).".torrent";
				$uploaded_file = getUploadsPath()."/".$name;
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
header("Location: ".$_SERVER['PHP_SELF'].'?result='.intval($success));

?>
