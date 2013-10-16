<?php
require_once( dirname(__FILE__)."/../../php/xmlrpc.php" );
require_once( dirname(__FILE__)."/../../php/Torrent.php" );
eval( getPluginConf( 'create' ) );

ignore_user_abort(true);
set_time_limit(0);

if(isset($_REQUEST['cmd']))
{
	$error = '';
	$cmd = $_REQUEST['cmd'];
	switch($cmd)
	{
		case "start":
		{
		        $error = 'theUILang.cantExecExternal';
		        if(isset($_REQUEST['path_edit']))
		        {
		        	$path_edit = trim($_REQUEST['path_edit']);
				if(is_dir($path_edit))
					$path_edit = addslash($path_edit);
		        	if(rTorrentSettings::get()->correctDirectory($path_edit))
				{
					$taskNo = time();
					$randName = getTempDirectory()."rutorrent-".getUser().$taskNo.".prm";
					file_put_contents( $randName, serialize( $_REQUEST ) );
					chmod($randName,0644);
					$piece_size = 262144;
					if(isset($_REQUEST['piece_size']))
						$piece_size = $_REQUEST['piece_size']*1024;
	       				if(!$pathToCreatetorrent || ($pathToCreatetorrent==""))
						$pathToCreatetorrent = $useExternal;
					if($useExternal=="mktorrent")
						$piece_size = log($piece_size,2);
					else
					if($useExternal===false)
						$useExternal = "inner";
					$req = new rXMLRPCRequest( 
						new rXMLRPCCommand( "execute", array(
	        	        			"sh", "-c",
						        escapeshellarg($rootPath.'/plugins/create/'.$useExternal.'.sh')." ".
							$taskNo." ".
							escapeshellarg(getPHP())." ".
							escapeshellarg($pathToCreatetorrent)." ".
							escapeshellarg($path_edit)." ".
							$piece_size." ".
							escapeshellarg(getUser())." ".
							escapeshellarg(getTempDirectory())." &")));
					if($req->success())
						$ret = array( "no"=>intval($taskNo), "errors"=>array(), "status"=>-1, "out"=>"" );
				}
				else
					$error = 'theUILang.incorrectDirectory';
			}
			break;
		}
		case "check":
		{
		        if(isset($_REQUEST['no']))
		        {
		        	$taskNo = $_REQUEST['no'];
				$dir = getTempDirectory().getUser().$taskNo;
				if(is_file($dir.'/pid') && is_readable($dir.'/pid'))
				{
					$pid = trim(file_get_contents($dir.'/pid'));
					$status = -1;
					if(is_file($dir.'/status') && is_readable($dir.'/status'))
						$status = trim(file_get_contents($dir.'/status'));
					$log=array();
					if(is_file($dir.'/log') && is_readable($dir.'/log'))
					{
						$lines = file($dir.'/log');
						foreach( $lines as $line )
						{
							$pos = strrpos($line,"\r");
							if($pos!==false)
							{
								$line = rtrim(substr($line,$pos+1));
								if(strlen($line)==0)
									continue;
							}
							if(strrpos($line,chr(8))!==false)
							{
								$len = strlen($line);
								$res = array();
								for($i=0; $i<$len; $i++)
								{
									if($line[$i]==chr(8))
										array_pop($res);
									else
										$res[] = $line[$i];
								}
								$line = implode('',$res);
							}
							$log[] = rtrim($line);
						}
					}
					if(count($log)>MAX_CONSOLE_SIZE)
						array_splice($log,0,count($log)-MAX_CONSOLE_SIZE);

					$errors=array();
					if(is_file($dir.'/errors') && is_readable($dir.'/errors'))
						$errors = array_map('trim', file($dir.'/errors'));
					$out = '';
					if(is_file($dir.'/out') && is_readable($dir.'/out'))
						$out = trim(file_get_contents($dir.'/out'));
					if($status>=0)
					{
						$req = new rXMLRPCRequest( 
							new rXMLRPCCommand( "execute", array("rm","-fr",$dir) ) );
						$req->run();
						@unlink(getTempDirectory()."rutorrent-".getUser().$taskNo.".prm");
					}
					$ret = array( 
						"no"=>intval($taskNo),
					        "status"=>$status,
				        	"pid"=>intval($pid),
					        "out"=>$out,
						"log"=>$log,
						"errors"=>$errors);
				}
			}
			break;
		}
		case "kill":
		{
		        if(isset($_REQUEST['no']))
		        {
		        	$taskNo = $_REQUEST['no'];
				$dir = getTempDirectory().getUser().$taskNo;
				if(is_file($dir.'/pid') && is_readable($dir.'/pid'))
				{
					$pid = trim(file_get_contents($dir.'/pid'));
					$req = new rXMLRPCRequest( 
//						new rXMLRPCCommand( "execute", array(getExternal("pkill"),"-9","-P",$pid) ) );
						new rXMLRPCCommand( "execute", array("sh","-c","kill -9 `".getExternal("pgrep")." -P ".$pid."`") ) );
					if($req->success())
						$ret = array( "no"=>$taskNo );	
					$req = new rXMLRPCRequest( 
						new rXMLRPCCommand( "execute", array("rm","-fr",$dir) ) );
					$req->run();
					@unlink(getTempDirectory()."rutorrent-".getUser().$taskNo.".prm");
				}
			}
			break;
		}
		case "get":
		{
			if(isset($_REQUEST['tname']))
			{
				$torrent = new Torrent( getUploadsPath()."/".$_REQUEST['tname'] );
				if( !$torrent->errors() )
					$torrent->send();
			}
			exit("Can't send torrent file.");
		}
	}
}

if(empty($ret))
	$ret = array( "no"=>0, "errors"=>array($error), "status"=>-1, "out"=>"" );
cachedEcho(json_encode($ret),"application/json");
