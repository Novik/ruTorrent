<?php
	require_once( '../../php/util.php' );
	require_once( '../../php/settings.php' );
	require_once( dirname(__FILE__)."/disk.php" );
	eval( FileUtil::getPluginConf( 'diskspace' ) );

	if( !isset($partitionDirectory) || is_null($partitionDirectory) )
	{
		// If we run locally && we the download directory seems to exists
		if ( User::isLocalMode() && rTorrentSettings::get()->linkExist && file_exists(rTorrentSettings::get()->directory) ) 
		{
			$partitionDirectory = array( rTorrentSettings::get()->directory ); // Then we can show the disk space of the download directory
		} 
		else 
		{
			$partitionDirectory = array( $topDirectory); // Else, we show $topDirectory by default as fallback
		}
	}
	elseif( !is_array($partitionDirectory) )
	{
		$partitionDirectory = array($partitionDirectory);
	}

	$diskSpace = rDiskSpace::load($partitionDirectory);

	$current = $diskSpace->updateOldest();

	$ret = array(
		"total" => 0,
		"free" => 0,
		"path" => "",
		"updated" => 0,
		"all" => array()
	);

	if( $current )
	{
		$ret["total"] = $current["total"];
		$ret["free"] = $current["free"];
		$ret["path"] = $current["path"];
		$ret["updated"] = $current["updated"];
	}

	$ret["all"] = $diskSpace->getAll();
	CachedEcho::send(JSON::safeEncode($ret),"application/json");
