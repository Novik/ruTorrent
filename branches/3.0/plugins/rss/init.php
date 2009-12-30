<?php
require_once( '../plugins/rss/conf.php' );

$st = getSettingsPath();
@rename($rootPath.'/plugins/rss/cache',$st.'/rss/cache');
@mkdir($st.'/rss');
@mkdir($st.'/rss/cache');
$needStart = true;
if($do_diagnostic)
{
	if(!$pathToPHP || ($pathToPHP==""))
		findRemoteEXE('php',"thePlugins.get('rss').showError('theUILang.rssPHPNotFound');",$remoteRequests);
	if(!$pathToCurl || ($pathToCurl==""))
	{
		findRemoteEXE('curl',"thePlugins.get('rss').showError('theUILang.rssCurlNotFound');",$remoteRequests);
		if(findEXE('curl')==false)
			$jResult.="plugin.showError('theUILang.rssCurlNotFound1');";
	}
	else
		if(!is_executable($pathToCurl))
			$jResult.="plugin.showError('theUILang.rssCurlNotFound1');";
	@chmod($st.'/rss',0777);
	@chmod($st.'/rss/cache',0777);
	if(is_dir($st.'/rss/cache') && !isUserHavePermission($theSettings->uid,$theSettings->gid,$st.'/rss/cache',0x0007))
	{
	        $needStart = false;
		$jResult.="plugin.disable(); plugin.showError('theUILang.rssCacheNotAvailable');";
	}
	@chmod($rootPath.'/plugins/rss/update.php',0644);
	if(!isUserHavePermission($theSettings->uid,$theSettings->gid,$rootPath.'/plugins/rss/update.php',0x0004))
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
	$req = new rXMLRPCRequest( new rXMLRPCCommand("schedule", array('rss',$startAt."",$interval."",'execute={sh,-c,'.getPHP().' '.$rootPath.'/plugins/rss/update.php'.' & exit 0}')) );
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
