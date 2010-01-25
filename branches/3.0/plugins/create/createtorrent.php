<?php
require_once( '../../php/xmlrpc.php' );
require_once( '../../php/Torrent.php' );
require_once( '../../php/rtorrent.php' );
eval(getPluginConf('create'));

function doExit($str)
{
	header("Location: ".$_SERVER['PHP_SELF'].'?result='.rawurlencode($str));
	exit;
}

if(isset($_REQUEST['result']))
	exit(rawurldecode($_REQUEST['result']));

ignore_user_abort(true);
set_time_limit(0);
if(isset($_REQUEST['path_edit']))
{
	$comment = '';
	$announce_list = array(); 
	$trackers = array(); 
	$piece_size = 256;
	$trackersCount = 0;
	if(isset($_REQUEST['piece_size']))
		$piece_size = $_REQUEST['piece_size'];
	if(isset($_REQUEST['trackers']))
	{
		$arr = explode("\r",$_REQUEST['trackers']);
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
	$path_edit = trim($_REQUEST['path_edit']);
	if(is_dir($path_edit))
		$path_edit = addslash($path_edit);
	$randName = null;
	if(strpos($path_edit,$topDirectory)===0)
	{
	        if($useExternal!==false)
	        {
	        	$ps = $piece_size*1024;
	        	$randName = '/tmp/rutorrent-create-'.rand().'.torrent';
        		if(!$pathToCreatetorrent || ($pathToCreatetorrent==""))
				$pathToCreatetorrent = $useExternal;
			if($useExternal=="transmissioncli")
				exec($pathToCreatetorrent.' -n '.escapeshellarg($path_edit).' '.$randName,$results,$return);
			else
			if($useExternal=="mktorrent")
			{
				$ps = log($ps,2);
		        	exec($pathToCreatetorrent.' -l '.$ps.' -a dummy  -o '.$randName.' '.escapeshellarg($path_edit),$results,$return);
			}
			else
		        	exec($pathToCreatetorrent.' -l '.$ps.' -a dummy '.escapeshellarg($path_edit).' '.$randName,$results,$return);
	        	if(!$return)
	        	{
	        		$torrent = new Torrent($randName);
	        		$torrent->clear_announce();
	        		if(count($announce_list)>0)
	        		{
	        			$torrent->announce($announce_list[0][0]);
					if($trackersCount>1)
						$torrent->announce_list($announce_list);
	        		}
	        		@unlink($randName);
	        	}
	        	else
         		        doExit('log(theUILang.cantExecExternal+" ('.$pathToCreatetorrent.')");');
	        }
	        else
	        {
			if(count($announce_list)>0)
			{
				$torrent = new Torrent($path_edit,$announce_list[0][0],$piece_size);
				if($trackersCount>1)
					$torrent->announce_list($announce_list);
			}
			else
                	        $torrent = new Torrent($path_edit,array(),$piece_size);
		}
		if(isset($_REQUEST['comment']))
		{
			$comment = trim($_REQUEST['comment']);
			if(strlen($comment))
				$torrent->comment($comment);
		}
	        if(isset($_REQUEST['private']))
			$torrent->is_private(true);
		$fname = getUploadsPath()."/".$torrent->info['name'].'.torrent';
		if(isset($_REQUEST['start_seeding']))
		{
        		$path_edit = dirname($path_edit);
			if($resumed = rTorrent::fastResume($torrent,$path_edit))
				$torrent = $resumed;
			$torrent->save($fname);
			@chmod(fname,0666);
			rTorrent::sendTorrent($fname, true, true, $path_edit, null, $saveUploadedTorrents, isLocalMode() );
        	}
        	else
	        	if($saveUploadedTorrents)
        		{
				$torrent->save($fname);
				@chmod(fname,0666);
			}
		$torrent->send();
	}
       	else
	        doExit('log(theUILang.incorrectDirectory+" ('.$path_edit.')");');
}
?>