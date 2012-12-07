<?php

eval( getPluginConf( 'screenshots' ) );

if(!$theSettings->isPluginRegistered("explorer"))
	require_once( "ffmpeg.php" );

$st = ffmpegSettings::load();
$jResult.=("plugin.ffmpegSettings = ".json_encode($st->get())."; plugin.extensions = ".json_encode($extensions).";");

$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
