<?php
eval(getPluginConf($plugin["name"]));

$st = getSettingsPath();
makeDirectory( array($st.'/rss',$st.'/rss/cache') );
$needStart = isLocalMode() && $theSettings->linkExist;
if($needStart)
{
	$tm = getdate();
	$startAt = mktime($tm["hours"],
		((integer)($tm["minutes"]/$updateInterval))*$updateInterval+$updateInterval,
		0,$tm["mon"],$tm["mday"],$tm["year"])-$tm[0];
	if($startAt<0)
		$startAt = 0;
	$interval = $updateInterval*60;
	$req = new rXMLRPCRequest( new rXMLRPCCommand("schedule", array('rss'.getUser(),$startAt."",$interval."",
		getCmd('execute').'={sh,-c,'.escapeshellarg(getPHP()).' '.escapeshellarg($rootPath.'/plugins/rss/update.php').' '.escapeshellarg(getUser()).' & exit 0}')) );
	if($req->run() && !$req->fault)
	{
		require_once($rootPath.'/plugins/rss/rss.php');
		$mngr = new rRSSManager();
		$mngr->setStartTime($startAt);
		$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
	}
	else
		$jResult .= "plugin.disable(); log('rss: '+theUILang.pluginCantStart);";
}
?>