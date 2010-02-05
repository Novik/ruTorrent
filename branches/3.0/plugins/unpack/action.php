<?php
require_once( '../../php/xmlrpc.php' );
require_once( '../../php/lfs.php' );
eval( getPluginConf( 'unpack' ) );

ignore_user_abort(true);
set_time_limit(0);

if(isset($_REQUEST['cmd']))
{
	$cmd = $_REQUEST['cmd'];
	switch($cmd)
	{
		case "start":
		{
			if(isset($_REQUEST['hash']) && isset($_REQUEST['dir']))
			{
			        if(isset($_REQUEST['no']) && isset($_REQUEST['mode']))
			        {
					$req = new rXMLRPCRequest( 
						new rXMLRPCCommand( "f.get_frozen_path", array($_REQUEST['hash'],intval($_REQUEST['no'])) ));
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
						$outPath = rawurldecode($_REQUEST['dir']);
						if(empty($outPath))
							$outPath = dirname($filename);
						if(LFS::is_file($filename) && !empty($outPath))
						{
						        $taskNo = rand();
							$logPath = '/tmp/rutorrent-unpack-log.'.$taskNo;
							$statusPath = '/tmp/rutorrent-unpack-status.'.$taskNo;
							$mode = $_REQUEST['mode'];
							if(empty($pathToUnrar))
								$pathToUnrar = "unrar";
							if(empty($pathToUnzip))
								$pathToUnzip = "unzip";
							$arh = (($mode == "zip") ? $pathToUnzip : $pathToUnrar);
							$c = new rXMLRPCCommand( "execute", array(
								        $rootPath.'/plugins/unpack/un'.$mode.'_file.sh',
									$arh,
									$filename,
									addslash($outPath),
									$logPath,
									$statusPath ));
							if(isset($_REQUEST['all']))
								$c->addParameter("-v");
							$req = new rXMLRPCRequest( $c );
							if($req->run())
								$ret = "{ no: ".$taskNo.", name: '".addslashes($filename)."', out: '".addslashes($outPath)."' }";
						}
					}
				}
				else
				{
					$req = new rXMLRPCRequest( 
						new rXMLRPCCommand( "d.get_base_path", $_REQUEST['hash'] ));
					if($req->success())
					{
						$basename = $req->val[0];
						if($basename=='')
						{
							$req = new rXMLRPCRequest( array(
								new rXMLRPCCommand( "d.open", $_REQUEST['hash'] ),
								new rXMLRPCCommand( "d.get_base_path", $_REQUEST['hash'] ),
								new rXMLRPCCommand( "d.close", $_REQUEST['hash'] ) ) );
							if($req->success())
								$basename = $req->val[1];
						}
						$outPath = rawurldecode($_REQUEST['dir']);
						$req = new rXMLRPCRequest( 
							new rXMLRPCCommand( "f.multicall", array($_REQUEST['hash'],"","f.get_path=") ));
						if($req->success())
						{
						        $rarPresent = false;
						        $zipPresent = false;
							foreach($req->val as $no=>$name)
							{
								if(USE_UNRAR && (preg_match("'.*\.(rar|r\d\d|\d\d\d)$'si", $name)==1))
									$rarPresent = true;
								else
								if(USE_UNZIP && (preg_match("'.*\.zip$'si", $name)==1))
									$zipPresent = true;
							}
							$mode = ($rarPresent && $zipPresent) ? 'all' : ($rarPresent ? 'rar' : ($zipPresent ? 'zip' : null));
							if($mode)
							{
							        $taskNo = rand();
								$logPath = '/tmp/rutorrent-unpack-log.'.$taskNo;
								$statusPath = '/tmp/rutorrent-unpack-status.'.$taskNo;
								if(empty($pathToUnrar))
									$pathToUnrar = "unrar";
								if(empty($pathToUnzip))
									$pathToUnzip = "unzip";
								$arh = (($mode == "zip") ? $pathToUnzip : $pathToUnrar);
								if(is_dir($basename))
								{
									$postfix = "_dir";
									if(empty($outPath))
										$outPath = $basename;
									$basename = addslash($basename);

								}
								else
								{
									$postfix = "_file";
									if(empty($outPath))
										$outPath = dirname($basename);
								}
								$req = new rXMLRPCRequest( new rXMLRPCCommand( "execute", array(
									        $rootPath.'/plugins/unpack/un'.$mode.$postfix.'.sh',
										$arh,
										$basename,
										addslash($outPath),
										$logPath,
										$statusPath,
										$pathToUnzip )));
								if($req->run())
									$ret = "{ no: ".$taskNo.", name: '".addslashes($basename)."', out: '".addslashes($outPath)."' }";
							}
						}
					}
				}
			}
			if(empty($ret))
				$ret = "{ no: -1 }";
  	                break;

		}
		case "check":
		{
		        $arr = array();
			if(!isset($HTTP_RAW_POST_DATA))
				$HTTP_RAW_POST_DATA = file_get_contents("php://input");
			if(isset($HTTP_RAW_POST_DATA))
			{
				$vars = split('&', $HTTP_RAW_POST_DATA);
				foreach($vars as $var)
				{
					$parts = split("=",$var);
					if($parts[0]=="no")
					{
						$taskNo = trim($parts[1]);
						$logPath = '/tmp/rutorrent-unpack-log.'.$taskNo;
						$statusPath = '/tmp/rutorrent-unpack-status.'.$taskNo;
						if(is_file($statusPath) && is_readable($statusPath))
						{
							$status = @file_get_contents($statusPath);
							if($status===false)
								$status = -1;
							else
								$status = trim($status);
							if(preg_match( '/^\d*$/',trim($status)) != 1)
								$status = -1;
							$errors = false;
							if(($status!=0) || SHOW_LOG_ON_SUCCESS)
								$errors = @file($logPath);
							if($errors===false)
								$errors=array();
							$errors = array_map('trim', $errors);
							$arr[] = "{ no: ".$taskNo.", status: ".$status.", errors: [".
								implode(",", array_map('quoteAndDeslashEachItem', $errors))."]}";
							$req = new rXMLRPCRequest( array(
								new rXMLRPCCommand( "execute", array("rm",$statusPath) ),
								new rXMLRPCCommand( "execute", array("rm",$logPath) ) ));
							$req->run();
						}
					}
				}
			}
			$ret = "[".implode(",",$arr)."]";
			break;
		}
	}
}

if(!empty($ret))
{
	header("Content-Type: application/json; charset=UTF-8");
	header("Content-Length: ".strlen($ret));
	echo $ret;
}
?>
