<?php
eval(getPluginConf('datadir'));

if($do_diagnostic && ($datadir_runmode=="rtorrent"))
	findRemoteEXE('php',"thePlugins.get('datadir').showError('theUILang.datadirPHPNotFound');",$remoteRequests);
$theSettings->registerPlugin("datadir");

?>