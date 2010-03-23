<?php
eval(getPluginConf('rss'));

$st = getSettingsPath();
@rename($rootPath.'/plugins/rss/cache',$st.'/rss/cache');
@mkdir($st.'/rss');
@mkdir($st.'/rss/cache');
$needStart = isLocalMode() && $theSettings->linkExist;
if($do_diagnostic && $theSettings->linkExist)
{
	if((!$pathToPHP || ($pathToPHP=="")) && $needStart)
		findRemoteEXE('php',"thePlugins.get('rss').showError('theUILang.rssPHPNotFound');",$remoteRequests);
	if(!$pathToCurl || ($pathToCurl==""))
	{
	        if($needStart)
			findRemoteEXE('curl',"thePlugins.get('rss').showError('theUILang.rssCurlNotFound');",$remoteRequests);
		if(findEXE('curl')==false)
			$jResult.="plugin.showError('theUILang.rssCurlNotFound1');";
	}
	else
		if(!is_executable($pathToCurl))
			$jResult.="plugin.showError('theUILang.rssCurlNotFound1');";
	@chmod($st.'/rss',0777);
	@chmod($st.'/rss/cache',0777);
	if($needStart && is_dir($st.'/rss/cache') && !isUserHavePermission($theSettings->uid,$theSettings->gid,$st.'/rss/cache',0x0007))
	{
	        $needStart = false;
		$jResult.="plugin.disable(); plugin.showError('theUILang.rssCacheNotAvailable');";
	}
	@chmod($rootPath.'/plugins/rss/update.php',0644);
	if($needStart && !isUserHavePermission($theSettings->uid,$theSettings->gid,$rootPath.'/plugins/rss/update.php',0x0004))
	{
	        $needStart = false;
		$jResult.="plugin.disable(); plugin.showError('theUILang.rssUpdaterNotAvailable');";
	}
}
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
		$theSettings->registerPlugin("rss");
	}
	else
		$jResult .= "plugin.disable(); log('rss: '+theUILang.pluginCantStart);";
}
?>
