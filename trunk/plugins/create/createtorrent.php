<?php

if( count( $argv ) > 2 )
	$_SERVER['REMOTE_USER'] = $argv[2];
if( count( $argv ) > 1 )
{
	require_once( '../../php/xmlrpc.php' );
	require_once( '../../php/Torrent.php' );
	require_once( '../../php/rtorrent.php' );
	eval(getPluginConf('create'));
	
	if(function_exists('ini_set'))
	{
		ini_set('display_errors',true);
		ini_set('log_errors',false);
	}

	$taskNo = $argv[1];
	$fname = getTempDirectory()."rutorrent-".getUser().$taskNo.".prm";
	if(is_file($fname) && is_readable($fname))
	{
		$request = unserialize(file_get_contents( $fname ));
		$comment = '';
		$announce_list = array(); 
		$trackers = array(); 
		$trackersCount = 0;
		if(isset($request['trackers']))
		{
			$arr = explode("\r",$request['trackers']);
			foreach( $arr as $key => $value )
			{
				$value = trim($value);
				if(strlen($value))
				{
					$trackers[] = $value;
					$trackersCount = $trackersCount+1;
				}
				else
				{
					if(count($trackers)>0)
					{
						$announce_list[] = $trackers;
						$trackers = array();
					}
				}
			}
		}
		if(count($trackers)>0)
			$announce_list[] = $trackers;
		$path_edit = trim($request['path_edit']);
		$piece_size = $request['piece_size'];
		$callback_log = create_function('$msg', '$fp=fopen("php://stderr","w"); fputs($fp, $msg."\n"); fclose($fp);' );
		$callback_err = create_function('$msg', '$fp=fopen("php://stdout","w"); fputs($fp, $msg."\n"); fclose($fp);' );

		if(count($announce_list)>0)
		{
			$torrent = new Torrent($path_edit,$announce_list[0][0],$piece_size,$callback_log,$callback_err);
			if($trackersCount>1)
				$torrent->announce_list($announce_list);
		}
		else
               	        $torrent = new Torrent($path_edit,array(),$piece_size,$callback_log,$callback_err);

		if(isset($request['comment']))
		{
			$comment = trim($request['comment']);
			if(strlen($comment))
				$torrent->comment($comment);
		}
	        if(isset($request['private']))
			$torrent->is_private(true);
		$fname = getUniqueUploadedFilename($torrent->info['name'].'.torrent');
		if(isset($request['start_seeding']))
		{
			if(is_dir($path_edit))
				$path_edit = addslash($path_edit);
        		$path_edit = dirname($path_edit);
			if($resumed = rTorrent::fastResume($torrent,$path_edit))
				$torrent = $resumed;
			$torrent->save($fname);
			rTorrent::sendTorrent($torrent, true, true, $path_edit, null, true, isLocalMode() );
			if($resumed)
			{
				if(isset($torrent->{'rtorrent'}))
					unset($torrent->{'rtorrent'});
				if(isset($torrent->{'libtorrent_resume'}))
					unset($torrent->{'libtorrent_resume'});
				$torrent->save($fname);
			}
        	}
        	else
			$torrent->save($fname);
		@chmod($fname,$profileMask & 0666);
		file_put_contents( getTempDirectory().getUser().$taskNo."/out", getFileName($fname));
		exit(0);
	}
	exit(1);
}
