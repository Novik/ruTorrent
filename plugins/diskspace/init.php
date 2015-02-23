<?php

eval( getPluginConf( $plugin["name"] ) );

if(!function_exists('disk_total_space') || !function_exists('disk_free_space') ||
	(disk_total_space($partitionDirectory)===false) || (disk_free_space($partitionDirectory)===false))
	$jResult .= "plugin.disable();";
else
{
	$jResult.="plugin.interval = ".$diskUpdateInterval."; plugin.notifySpaceLimit = ".($notifySpaceLimit*1024*1024).";";
	$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
}
