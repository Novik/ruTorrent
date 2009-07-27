<?php
require_once( '../../config.php' );
require_once( '../../xmlrpc.php' );
require_once( '../../Torrent.php' );
require_once( 'conf.php' );

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
	$path_edit = $_REQUEST['path_edit'];
	if($path_edit[strlen($path_edit)-1]=='/')
		$path_edit = substr($path_edit,0,-1);	
	$path_edit = trim($path_edit);	
	if(strlen($path_edit))
	{
	        if($useExternal)
	        {
	        	$ps = $piece_size*1024;
	        	$randName = '/tmp/rutorrent-create-'.rand().'.torrent';
        		if(!$pathToCreatetorrent || ($pathToCreatetorrent==""))
				$pathToCreatetorrent = "createtorrent";
	        	exec($pathToCreatetorrent.' -l '.$ps.' -a dummy '.escapeshellcmd($path_edit).' '.$randName,$results,$return);
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
	        		exit(1);
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
		if(isset($_REQUEST['start_seeding']))
		{
			$fname = "../../".$uploads."/".$torrent->info['name'].'.torrent';
			$torrent->save($fname);
			$fname = realpath($fname);
			@chmod(fname,0666);
        		$path_edit = dirname($path_edit);
			$cmd = new rXMLRPCCommand( 'load_start_verbose', array( $fname, "d.set_directory=\"".$path_edit."\"" ) );
			if(isInvalidUTF8($comment))
				$comment = win2utf($comment);
			if(strlen($comment)>0)
				$cmd->addParameter( "d.set_custom2=VRS24mrker".rawurlencode($comment) );
			$req = new rXMLRPCRequest( $cmd );
			$req->run();	
        	}
		$torrent->send();
	}
}
?>