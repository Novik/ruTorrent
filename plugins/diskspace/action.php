<?php
	require_once( '../../php/util.php' );
	require_once( 'conf.php' );
	cachedEcho('{ "total": '.disk_total_space(($dirToMonSpace?$dirToMonSpace:$topDirectory)).', "free": '.disk_free_space(($dirToMonSpace?$dirToMonSpace:$topDirectory)).' }',"application/json");
