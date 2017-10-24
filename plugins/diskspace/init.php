<?php

eval( getPluginConf( $plugin["name"] ) );

if( is_null($partitionDirectory) )
{
	// If we run locally && we the download directory seems to exists
	if ( isLocalMode() && rTorrentSettings::get()->linkExist && file_exists(rTorrentSettings::get()->directory) ) 
	{
		$partitionDirectory = rTorrentSettings::get()->directory; // Then we can show the disk space of the download directory
	} 
	else 
	{
		$partitionDirectory = &$topDirectory; // Else, we show $topDirectory by default as fallback
	}
}

if(!function_exists('disk_total_space') || !function_exists('disk_free_space') ||
	(disk_total_space($partitionDirectory)===false) || (disk_free_space($partitionDirectory)===false))
	$jResult .= "plugin.disable();";
else
{
	$jResult.="plugin.interval = ".$diskUpdateInterval."; plugin.notifySpaceLimit = ".($notifySpaceLimit*1024*1024).";";
	$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
}
