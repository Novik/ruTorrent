<?php
	require_once( '../../php/util.php' );
	cachedEcho('{ "total": '.disk_total_space($topDirectory).', "free": '.disk_free_space($topDirectory).' }',"application/json");
