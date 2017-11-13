<?php

eval( getPluginConf( 'spectrogram' ) );

$jResult.=("plugin.extensions = ".safe_json_encode($extensions).";");

$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
