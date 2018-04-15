<?php

if( count( $argv ) > 2 )
	$_SERVER['REMOTE_USER'] = $argv[2];
if( count( $argv ) > 1 )
{
	require_once( dirname(__FILE__).'/../../php/xmlrpc.php' );
	require_once( dirname(__FILE__).'/../../php/Torrent.php' );
	require_once( dirname(__FILE__).'/../../php/rtorrent.php' );
	require_once( dirname(__FILE__).'/../_task/task.php' );	
	eval(getPluginConf('create'));
	
	if(function_exists('ini_set'))
	{
		ini_set('display_errors',true);
		ini_set('log_errors',false);
	}

	$taskNo = $argv[1];
	$fname = rTask::formatPath($taskNo).'/params';
	$tname = rTask::formatPath($taskNo)."/temp.torrent";
	if(is_file($fname) && is_readable($fname) &&
		is_file($tname) && is_readable($tname))
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
       		$torrent = new Torrent($tname);
       		$torrent->clear_announce();
       		if(count($announce_list)>0)
       		{
       			$torrent->announce($announce_list[0][0]);
			if($trackersCount>1)
				$torrent->announce_list($announce_list);
       		}
		if(isset($request['comment']))
		{
			$comment = trim($request['comment']);
			if(strlen($comment))
				$torrent->comment($comment);
		}
	        if($request['private'])
		{
			$torrent->is_private(true);
		}
		if(isset($request['source']))
		{
			$source = trim($request['source']);
			if(strlen($source))
				$torrent->source($source);
		}
		$fname = rTask::formatPath($taskNo)."/result.torrent";
		$torrent->save($fname);

		if($request['start_seeding'])
		{
			$fname = getUniqueUploadedFilename($torrent->info['name'].'.torrent');
			$path_edit = trim($request['path_edit']);
			if(is_dir($path_edit))
				$path_edit = addslash($path_edit);
	        	if(rTorrentSettings::get()->correctDirectory($path_edit))
			{
        			$path_edit = dirname($path_edit);
				if($resumed = rTorrent::fastResume($torrent,$path_edit))
					$torrent = $resumed;
				$torrent->save($fname);
				rTorrent::sendTorrent($torrent, true, true, $path_edit, null, true, isLocalMode() );
				@chmod($fname,$profileMask & 0666);
			}
		}
		exit(0);
	}
	exit(1);
}
