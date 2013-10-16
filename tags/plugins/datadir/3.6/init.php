<?php
eval(getPluginConf($plugin["name"]));

if($do_diagnostic)
	findRemoteEXE('php',"thePlugins.get('datadir').showError('theUILang.datadirPHPNotFound');",$remoteRequests);
$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
