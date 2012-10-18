<?php
eval(getPluginConf($plugin["name"]));
require_once( '../plugins/trafic/ratios.php' );

$st = getSettingsPath();
makeDirectory( array( $st.'/trafic', $st.'/trafic/trackers', $st.'/trafic/torrents') );
$req = new rXMLRPCRequest( $theSettings->getScheduleCommand("trafic",$updateInterval,
	getCmd('execute').'={sh,-c,'.escapeshellarg(getPHP()).' '.escapeshellarg($rootPath.'/plugins/trafic/update.php').' '.escapeshellarg(getUser()).' & exit 0}' ) );
if($req->run() && !$req->fault)
       	$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
else
       	$jResult .= "plugin.disable(); noty('trafic: '+theUILang.pluginCantStart,'error');";
$jResult .= "plugin.collectStatForTorrents = ".($collectStatForTorrents ? "true;" : "false;");
$jResult .= "plugin.updateInterval = ".$updateInterval.";";
$jResult .= "plugin.disableClearButton = ".($disableClearButton ? "true" : "false").";";
