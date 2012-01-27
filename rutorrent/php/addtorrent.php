<?php

require_once( 'Snoopy.class.inc');
require_once( 'rtorrent.php' );
set_time_limit(0);

$uploaded_file = '';
$success = false;
$status = null;

if(isset($_REQUEST['result']))
	cachedEcho('log(theUILang.addTorrent'.$_REQUEST['result'].');',"text/html");
$label = null;
if(isset($_REQUEST['label']))	
	$label = trim($_REQUEST['label']);
$dir_edit = null;
if(isset($_REQUEST['dir_edit']))
{
	$dir_edit = trim($_REQUEST['dir_edit']);
	if(!rTorrentSettings::get()->correctDirectory($dir_edit))
		$status = "FailedDirectory";
}
if(is_null($status))
{
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
				header("Location: ".$_SERVER['PHP_SELF'].'?result='.($success ? "Success" : "Failed") );
				exit();
			}
			else
			{
				$cli = new Snoopy();
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
				else
					$status = "FailedURL";
			}	
		}
}
if($success)
{
	@chmod($uploaded_file,$profileMask & 0666);
	$uploaded_file = realpath($uploaded_file);
	$torrent = new Torrent($uploaded_file);
	if($torrent->errors())
		$status = "FailedFile";
	if($torrent->errors() || (rTorrent::sendTorrent($uploaded_file,
		!isset($_REQUEST['torrents_start_stopped']),
		!isset($_REQUEST['not_add_path']),
		$dir_edit,$label,$saveUploadedTorrents,isset($_REQUEST['fast_resume']))===false))
	{
                unlink($uploaded_file);
                $success = false;
	}
}
if($success)
	$status = "Success";
else
	if(is_null($status))
		$status = "Failed";
header("HTTP/1.0 302 Moved Temporarily");
header("Location: ".$_SERVER['PHP_SELF'].'?result='.$status);
?>