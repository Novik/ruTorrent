<?php
eval(getPluginConf($plugin["name"]));
require_once( '../plugins/trafic/ratios.php' );

$st = getSettingsPath();
makeDirectory( array( $st.'/trafic', $st.'/trafic/trackers', $st.'/trafic/torrents') );

$tm = getdate();
$startAt = mktime($tm["hours"],
	((integer)($tm["minutes"]/$updateInterval))*$updateInterval+$updateInterval-1,
	0,$tm["mon"],$tm["mday"],$tm["year"])-$tm[0];
if($startAt<0)
	$startAt = 0;
$interval = $updateInterval*60;
$req = new rXMLRPCRequest( new rXMLRPCCommand("schedule", 
	array( "trafic".getUser(), $startAt."", $interval."", 
		getCmd('execute').'={sh,-c,'.escapeshellarg(getPHP()).' '.escapeshellarg($rootPath.'/plugins/trafic/update.php').' '.escapeshellarg(getUser()).' & exit 0}' ) ) );
if($req->run() && !$req->fault)
       	$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
else
       	$jResult .= "plugin.disable(); log('trafic: '+theUILang.pluginCantStart);";
$jResult .= "plugin.collectStatForTorrents = ".($collectStatForTorrents ? "true;" : "false;");
$jResult .= "plugin.updateInterval = ".$updateInterval.";";
$jResult .= "plugin.disableClearButton = ".($disableClearButton ? "true" : "false").";";
$jResult .= getRatiosStat();

?>