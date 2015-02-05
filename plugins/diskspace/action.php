<?php
	include 'conf.php';
	require_once( '../../php/util.php' );
	cachedEcho('{ "total": '.disk_total_space($partitionDirectory).', "free": '.disk_free_space($partitionDirectory).' }',"application/json");
