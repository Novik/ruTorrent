<?php
require_once( dirname(__FILE__).'/../_task/task.php' );
eval( FileUtil::getPluginConf( 'dump' ) );

$ret = array( "status"=>255, "errors"=>array("Can't retrieve information") );

if(isset($_REQUEST['hash']) && isset($_REQUEST['cmd']))
{
	switch($_REQUEST['cmd'])
	{
		case "dumptorrent":
		{
			$hash=$_REQUEST['hash'];
			$req = new rXMLRPCRequest( array(
				new rXMLRPCCommand("get_session"),
				new rXMLRPCCommand("d.get_tied_to_file",$hash)) );
			if($req->run() && !$req->fault)
			{
				$fname = $req->val[0].$hash.".torrent";
				if(empty($req->val[0]) || !is_readable($fname))
				{
					if(strlen($req->val[1]) && is_readable($req->val[1]))
						$fname = $req->val[1];
		    		else
						$fname = '';
				}
			}

			if($fname!=='')
			{
				$commands = array();
				$task = new rTask(array('requester'=>'dump', 'name'=>'dump'));
				$commands[] = Utility::getExternal("dumptorrent")." ".$arguments." ".escapeshellarg($fname);
				$ret = $task->start($commands, rTask::FLG_WAIT | rTask::FLG_DO_NOT_TRIM);
			}
		}
	}
}

CachedEcho::send(JSON::safeEncode($ret),"application/json");
