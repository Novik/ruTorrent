<?php
eval(getPluginConf('trafic'));

$st = getSettingsPath();
@rename($rootPath.'/plugins/trafic/stats',$st.'/trafic');
@mkdir($st.'/trafic');
@mkdir($st.'/trafic/trackers');
$needStart = true;
if($do_diagnostic)
{
	findRemoteEXE('php',"thePlugins.get('trafic').showError('theUILang.trafPHPNotFound');",$remoteRequests);
	@chmod($st.'/trafic',0777);
	@chmod($st.'/trafic/trackers',0777);
	if( (is_dir($st.'/trafic') && !isUserHavePermission($theSettings->uid,$theSettings->gid,$st.'/trafic',0x0007)) ||
	    (is_dir($st.'/trafic/trackers') && !isUserHavePermission($theSettings->uid,$theSettings->gid,$st.'/trafic/trackers',0x0007)))
	{
		$jResult.="plugin.disable(); plugin.showError('theUILang.trafStatsNotAvailable');";
		$needStart = false;
	}
	@chmod($rootPath.'/plugins/trafic/update.php',0644);
	if(!isUserHavePermission($theSettings->uid,$theSettings->gid,$rootPath.'/plugins/trafic/update.php',0x0004))
	{
		$jResult.="plugin.disable(); plugin.showError('theUILang.trafUpdaterNotAvailable');";
		$needStart = false;
	}
}
if($needStart)
{
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
        	$theSettings->registerPlugin("trafic");
	else
        	$jResult .= "plugin.disable(); log('trafic: '+theUILang.pluginCantStart);";
}
?>
