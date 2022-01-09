<?php

require_once( 'Snoopy.class.inc');
require_once( 'rtorrent.php' );
set_time_limit(0);

if(isset($_REQUEST['result']))
{
	if(isset($_REQUEST['json']))
		CachedEcho::send( '{ "result" : "'.$_REQUEST['result'][0].'" }',"application/json");
	else
	{
		$js = '';
		foreach( $_REQUEST['result'] as $ndx=>$result )
			$js.= ('noty("'.(isset($_REQUEST['name'][$ndx]) ? addslashes(rawurldecode(htmlspecialchars($_REQUEST['name'][$ndx]))).' - ' : '').
				'"+theUILang.addTorrent'.$_REQUEST['result'][$ndx].
				',"'.($_REQUEST['result'][$ndx]=='Success' ? 'success' : 'error').'");');
		CachedEcho::send($js,"text/html");
	}
}
else
{
	$uploaded_files = array();
	$label = null;
	if(isset($_REQUEST['label']))
		$label = trim($_REQUEST['label']);
	$dir_edit = null;
	if(isset($_REQUEST['dir_edit']))
	{
		$dir_edit = trim($_REQUEST['dir_edit']);
		if((strlen($dir_edit)>0) && !rTorrentSettings::get()->correctDirectory($dir_edit))
			$uploaded_files = array( array( 'status' => "FailedDirectory" ) );
	}
	if(empty($uploaded_files))
	{
		if(isset($_FILES['torrent_file']))
		{
			if( is_array($_FILES['torrent_file']['name']) )
			{
				for ($i = 0; $i<count($_FILES['torrent_file']['name']); ++$i)
				{
		                        $files[] = array
        		                (
                		            'name' => $_FILES['torrent_file']['name'][$i],
                        		    'tmp_name' => $_FILES['torrent_file']['tmp_name'][$i],
		                        );
        	        	}
			}
			else
				$files[] = $_FILES['torrent_file'];
			foreach( $files as $file )
			{
				$ufile = $file['name'];
				if(pathinfo($ufile,PATHINFO_EXTENSION)!="torrent")
					$ufile.=".torrent";
				$ufile = FileUtil::getUniqueUploadedFilename($ufile);
				$ok = move_uploaded_file($file['tmp_name'],$ufile);
				$uploaded_files[] = array( 'name'=>$file['name'], 'file'=>$ufile, 'status'=>($ok ? "Success" : "Failed") );
			}
		}
		else
		{
			if(isset($_REQUEST['url']))
			{
				$url = trim($_REQUEST['url']);
				$uploaded_url = array( 'name'=>$url, 'status'=>"Failed" );
				if(strpos($url,"magnet:")===0)
				{
					$uploaded_url['status'] = (rTorrent::sendMagnet($url,
						!isset($_REQUEST['torrents_start_stopped']),
						!isset($_REQUEST['not_add_path']),
						$dir_edit,$label) ? "Success" : "Failed" );
				}
				else
				{
					$cli = new Snoopy();
					if(@$cli->fetchComplex($url) && $cli->status>=200 && $cli->status<300)
					{
						$name = $cli->get_filename();
						if($name===false)
							$name = md5($url).".torrent";
						$name = FileUtil::getUniqueUploadedFilename($name);
						$f = @fopen($name,"w");
						if($f!==false)
						{
							@fwrite($f,$cli->results,strlen($cli->results));
							fclose($f);
							$uploaded_url['file'] = $name;
							$uploaded_url['status'] = "Success";
						}
					}
					else
						$uploaded_url['status'] = "FailedURL";
				}
				$uploaded_files[] = $uploaded_url;
			}
		}
	}
	$location = "Location: //".$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF'])."/addtorrent.php?";
	if(empty($uploaded_files))
		$uploaded_files = array( array( 'status' => "Failed" ) );
	foreach($uploaded_files as &$file)
	{
		if( ($file['status']=='Success') && isset($file['file']) )
		{
			$file['file'] = realpath($file['file']);
			@chmod($file['file'],$profileMask & 0666);
			$torrent = new Torrent($file['file']);
			if($torrent->errors())
			{
				@unlink($file['file']);
				$file['status'] = "FailedFile";
			}
			else
			{
				if(isset($_REQUEST['randomize_hash']))
					$torrent->info['unique'] = uniqid("rutorrent-",true);
				if(rTorrent::sendTorrent($torrent,
					!isset($_REQUEST['torrents_start_stopped']),
					!isset($_REQUEST['not_add_path']),
					$dir_edit,$label,$saveUploadedTorrents,isset($_REQUEST['fast_resume']))===false)
				{
					@unlink($file['file']);
					$file['status'] = "Failed";
				}
			}
		}
		$location.=('result[]='.$file['status'].'&');
		if( isset($file['name']) )
			$location.=('name[]='.rawurlencode($file['name']).'&');
	}
	header("HTTP/1.0 302 Moved Temporarily");
	if(isset($_REQUEST['json']))
		$location.='json=1';
	header($location);
}
