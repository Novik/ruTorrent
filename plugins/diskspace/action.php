<?php
	require_once( '../../php/util.php' );
	cachedEcho('{ "total": '.disk_total_space($_GET["p"]).', "free": '.disk_free_space($_GET["p"]).' }',"application/json");
