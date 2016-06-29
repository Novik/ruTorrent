<?php

eval( getPluginConf( 'screenshots' ) );

if(!$theSettings->isPluginRegistered("explorer"))
	require_once( "ffmpeg.php" );

$st = ffmpegSettings::load();
$jResult.=("plugin.ffmpegSettings = ".safe_json_encode($st->get())."; plugin.extensions = ".safe_json_encode($extensions).";");

$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
