<?php

eval( FileUtil::getPluginConf( $plugin["name"] ) );

$jResult.=("plugin.hideTrackers = ".JSON::safeEncode($hideTrackers).";");

$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
