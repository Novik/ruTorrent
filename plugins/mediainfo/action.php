<?php

require_once( '../_task/task.php' );
eval( getPluginConf( 'mediainfo' ) );

$ret = array( "status"=>255, "errors"=>array("Can't retrieve information") );

if(isset($_REQUEST['hash']) && 
	isset($_REQUEST['no']) &&
	isset($_REQUEST['cmd']))
{
	switch($_REQUEST['cmd'])
	{
		case "mediainfo":
		{
			$req = new rXMLRPCRequest( new rXMLRPCCommand( "f.get_frozen_path", array($_REQUEST['hash'],intval($_REQUEST['no']))) );
			if($req->success())
			{
				$filename = $req->val[0];
				if($filename=='')
				{
					$req = new rXMLRPCRequest( array(
						new rXMLRPCCommand( "d.open", $_REQUEST['hash'] ),
						new rXMLRPCCommand( "f.get_frozen_path", array($_REQUEST['hash'],intval($_REQUEST['no'])) ),
						new rXMLRPCCommand( "d.close", $_REQUEST['hash'] ) ) );
					if($req->success())
						$filename = $req->val[1];
				}
				if($filename!=='')
				{
					$commands = array();
					$commands[] = getExternal("mediainfo")." ".escapeshellarg($filename);
					$ret = rTask::start($commands,0);
				}
			}
			break;
		}
	}
}

cachedEcho(json_encode($ret),"application/json");
