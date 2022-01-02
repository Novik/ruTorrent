<?php

require_once( dirname(__FILE__).'/../_task/task.php' );
eval( FileUtil::getPluginConf( 'spectrogram' ) );

$ret = array();
if(isset($_REQUEST['cmd']))
{
	switch($_REQUEST['cmd'])
	{
		case "sox":
		{
			if(isset($_REQUEST['hash']) && 
				isset($_REQUEST['no']))
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
						$mediafile = basename($filename);
						$commands = array();
						$name = '"${dir}"/frame.png';
						$commands[] = Utility::getExternal("sox").
								" ".escapeshellarg($filename)." ".
								implode( " ", array_map( "escapeshellarg", explode(" ",$arguments) ) ).
								' -t '.escapeshellarg($mediafile).' -o '.
								$name;
						$commands[] = '{';
						$commands[] = '>-=*=-';
						$commands[] = '}';
						$commands[] = 'chmod a+r "${dir}"/frame.png';
					}
					$task = new rTask( array
					( 
					        'arg' => FileUtil::getFileName($filename),
						'requester'=>'spectrogram',
						'name'=>'sox', 
						'hash'=>$_REQUEST['hash'], 
						'no'=>$_REQUEST['no'] 
					) );
					$ret = $task->start($commands, rTask::FLG_ONE_LOG | rTask::FLG_STRIP_LOGS);
				}
			}
			break;
		}
		case "soxgetimage":
		{
			$dir = rTask::formatPath( $_REQUEST['no'] );
			$filename = $dir.'/frame.png';
			SendFile::send($filename, 'image/png', $_REQUEST['file'].".png");
			exit();
		}
	}
}

CachedEcho::send(JSON::safeEncode($ret),"application/json");
