<?php
	require_once( '../../php/util.php' );
	$content = '{ total: '.disk_total_space($topDirectory).', free: '.disk_free_space($topDirectory).' }';
	header( "Content-Length: ".strlen( $content ) );
	header("Content-Type: application/json; charset=UTF-8");
	echo $content;
?>
