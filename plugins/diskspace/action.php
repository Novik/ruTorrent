<?php
	require_once( '../../php/util.php' );
	require_once( '../../php/settings.php' );
	eval( getPluginConf( 'diskspace' ) );
	cachedEcho('{ "total": '.disk_total_space($partitionDirectory).', "free": '.disk_free_space($partitionDirectory).' }',"application/json");
