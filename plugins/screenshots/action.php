<?php

require_once( dirname(__FILE__).'/../_task/task.php' );
require_once( 'ffmpeg.php' );
eval( FileUtil::getPluginConf( 'screenshots' ) );

$ret = array();
if(isset($_REQUEST['cmd']))
{
	$st = ffmpegSettings::load();
	switch($_REQUEST['cmd'])
	{
		case "ffmpeg":
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
						$commands = array();
						$offs = $st->data['exfrmoffs'];
						$useWidth = $st->data['exusewidth'];
						for($i=0; $i<$st->data['exfrmcount']; $i++)
						{
							$name = '"${dir}"/frame'.$i.($st->data['exformat'] ? '.png' : '.jpg');
							$commands[] = Utility::getExternal("ffmpeg").
								' -ss '.$offs.
								" -i ".escapeshellarg($filename).
								' -y -vframes 1 -an '.
								($useWidth ? '-vf "scale='.$st->data['exfrmwidth'].':-1"' : '').
								' '.
								$name;
							$commands[] = '{';
							$commands[] = '>'.$i;
							$commands[] = '}';
							$offs += $st->data['exfrminterval'];
						}
						$commands[] = 'chmod a+r "${dir}"/frame*.*';
					}
					$task = new rTask( array
					( 
						'arg' => FileUtil::getFileName($filename),
						'requester'=>'screenshots',
						'name'=>'ffmpeg', 
						'hash'=>$_REQUEST['hash'], 
						'no'=>$_REQUEST['no'] 
					));
					$ret = $task->start($commands, rTask::FLG_NO_ERR);
				}
			}
			break;
		}
		case "ffmpeggetall":
		{
			$dir = rTask::formatPath( $_REQUEST['no'] );
			if(@chdir( $dir ))
			{
				$randName = FileUtil::getTempFilename('screenshots-detail');
				exec(escapeshellarg(Utility::getExternal('tar'))." -cf ".$randName." *.".($st->data['exformat'] ? 'png' : 'jpg'),$results,$return);
				if(is_file($randName))
				{
					SendFile::send( $randName, "application/x-tar",  $_REQUEST['file'].'.tar', false );
					unlink($randName);
					exit();
				}
			}
			header('HTTP/1.0 404 Not Found');
			exit();
		}
		case "ffmpeggetimage":
		{
			$dir = rTask::formatPath( $_REQUEST['no'] );
			$ext = ($st->data['exformat'] ? '.png' : '.jpg');
			$filename = $dir.'/frame'.$_REQUEST['fno'].$ext;
			SendFile::send($filename, $st->data['exformat'] ? 'image/png' : 'image/jpeg', $_REQUEST['file']."-".str_pad($_REQUEST['fno']+1, 3, "0", STR_PAD_LEFT).$ext);
			exit();
		}
		case "ffmpegset":
		{
			$ret = $st->set();
			break;
		}
	}
}

CachedEcho::send(JSON::safeEncode($ret),"application/json");
