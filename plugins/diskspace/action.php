<?php
	require_once( '../../php/util.php' );
	require_once( '../../php/settings.php' );
	eval( FileUtil::getPluginConf( 'diskspace' ) );

	if( is_null($partitionDirectory) )
	{
		// If we run locally && we the download directory seems to exists
		if ( User::isLocalMode() && rTorrentSettings::get()->linkExist && file_exists(rTorrentSettings::get()->directory) ) 
		{
			$partitionDirectory = rTorrentSettings::get()->directory; // Then we can show the disk space of the download directory
		} 
		else 
		{
			$partitionDirectory = &$topDirectory; // Else, we show $topDirectory by default as fallback
		}
	}
	$ret = array
	(
		"total" => 0,
		"free" => 0
	);
	if( is_dir($partitionDirectory) )
	{
		$ret["total"] = disk_total_space($partitionDirectory);
		$ret["free"] = disk_free_space($partitionDirectory);
	}
	CachedEcho::send(JSON::safeEncode($ret),"application/json");
