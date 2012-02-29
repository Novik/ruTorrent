<?php

if(!$theSettings->isPluginRegistered("explorer"))
	require_once( "ffmpeg.php" );

$st = ffmpegSettings::load();
$jResult.=("plugin.ffmpegSettings = ".json_encode($st->get()).";");

$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);

?>