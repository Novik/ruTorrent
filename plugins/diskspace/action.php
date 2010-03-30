<?php
	require_once( '../../php/util.php' );
	header("Content-Type: application/json; charset=UTF-8");
	cachedEcho('{ total: '.disk_total_space($topDirectory).', free: '.disk_free_space($topDirectory).' }');
?>
