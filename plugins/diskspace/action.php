<?php
	require_once( '../../php/util.php' );
	require_once( '../../php/settings.php' );
	eval( getPluginConf( 'diskspace' ) );

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
	cachedEcho('{ "total": '.disk_total_space($partitionDirectory).', "free": '.disk_free_space($partitionDirectory).' }',"application/json");
