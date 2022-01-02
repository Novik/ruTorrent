<?php

eval( FileUtil::getPluginConf( 'screenshots' ) );

if(!$theSettings->isPluginRegistered("explorer"))
	require_once( "ffmpeg.php" );

$st = ffmpegSettings::load();
$jResult.=("plugin.ffmpegSettings = ".JSON::safeEncode($st->get())."; plugin.extensions = ".JSON::safeEncode($extensions).";");

$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
