<?php

require_once( "xmlrpc.php" );
require_once( 'settings.php' );

define('FLAG_CANT_SHUTDOWN',	0x0080);
define('FLAG_CAN_CHANGE_LAUNCH',0x0100);

$theSettings = rTorrentSettings::get();
$jResult = "";

$cmd = isset($_REQUEST["cmd"]) ? $_REQUEST["cmd"] : "done";

$userPermissions = array( "__hash__"=>"plugins.dat" );
$cache = new rCache();
$cache->get($userPermissions);

if(!isset($HTTP_RAW_POST_DATA))
	$HTTP_RAW_POST_DATA = file_get_contents("php://input");
if(isset($HTTP_RAW_POST_DATA))
{
	$vars = explode('&', $HTTP_RAW_POST_DATA);
	foreach($vars as $var)
	{
		$parts = explode("=",$var);
		if($parts[0]=="plg")
		{
			$perms = $theSettings->getPluginData($parts[1]);
			switch($cmd)
			{
				case "unlaunch":
				{
					if(is_null($perms) || ($perms & FLAG_CAN_CHANGE_LAUNCH))
					{
						$userPermissions[$parts[1]] = false;
						$jResult.="thePlugins.get('".$parts[1]."').unlaunch();";
					}
				}
				case "done":
				{
					if(!is_null($perms) && !($perms & FLAG_CANT_SHUTDOWN))
					{
						$php = "../plugins/".$parts[1]."/done.php";
						if(is_file($php) && is_readable($php))
							require_once($php);
						$theSettings->unregisterPlugin($parts[1]);
						$jResult.="thePlugins.get('".$parts[1]."').remove();";
					}
					break;
				}
				case "launch":
				{
					if(is_null($perms) || ($perms & FLAG_CAN_CHANGE_LAUNCH))
					{
						$userPermissions[$parts[1]] = true;
						$jResult.="thePlugins.get('".$parts[1]."').launch();";
					}
					break;
				}

			}
		}
	}

	if($cmd=="done")
		$theSettings->store();
	else
		$cache->set($userPermissions);
}

CachedEcho::send($jResult,"application/javascript");
