<?php

eval( FileUtil::getPluginConf( 'spectrogram' ) );

$jResult.=("plugin.extensions = ".JSON::safeEncode($extensions).";");

$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
