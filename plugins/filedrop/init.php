<?php

eval( FileUtil::getPluginConf( 'filedrop' ) );

$jResult.=("plugin.maxfiles = ".$maxfiles.";\n".
	"plugin.maxfilesize = ".$maxfilesize.";\n".
	"plugin.queuefiles = ".$queuefiles.";\n");
$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
