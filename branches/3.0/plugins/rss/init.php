<?php
require_once( '../plugins/rss/conf.php' );
require_once( 'util.php' );

$st = getSettingsPath();
@rename($rootPath.'/plugins/rss/cache',$st.'/rss/cache');
@mkdir($st.'/rss');
@mkdir($st.'/rss/cache');
if($do_diagnostic)
{
	if(!$pathToPHP || ($pathToPHP==""))
		findRemoteEXE('php',"thePlugins.get('rss').showError('WUILang.rssPHPNotFound');",$remoteRequests);
	if(!$pathToCurl || ($pathToCurl==""))
	{
		findRemoteEXE('curl',"thePlugins.get('rss').showError('WUILang.rssCurlNotFound');",$remoteRequests);
		if(findEXE('curl')==false)
			$jEnd.="plugin.showError('WUILang.rssCurlNotFound1');";
	}
	else
		if(!is_executable($pathToCurl))
			$jEnd.="plugin.showError('WUILang.rssCurlNotFound1');";
	@chmod($st.'/rss',0777);
	@chmod($st.'/rss/cache',0777);
	if(is_dir($st.'/rss/cache') && !isUserHavePermission($theSettings->uid,$theSettings->gid,$st.'/rss/cache',0x0007))
		$jEnd.="plugin.showError('WUILang.rssCacheNotAvailable');";
	@chmod($rootPath.'/plugins/rss/update.php',0644);
	if(!isUserHavePermission($theSettings->uid,$theSettings->gid,$rootPath.'/plugins/rss/update.php',0x0004))
		$jEnd.="plugin.showError('WUILang.rssUpdaterNotAvailable');";
}
$tm = getdate();
$startAt = mktime($tm["hours"],
	((integer)($tm["minutes"]/$updateInterval))*$updateInterval+$updateInterval,
	0,$tm["mon"],$tm["mday"],$tm["year"])-$tm[0];
if($startAt<0)
	$startAt = 0;
$interval = $updateInterval*60;
if(!$pathToPHP || ($pathToPHP==""))
	$php = "php";
else
	$php = $pathToPHP;
send2RPC('<?xml version="1.0" encoding="UTF-8"?>'.
	'<methodCall>'.
	'<methodName>schedule</methodName>'.
	'<params>'.
	'<param><value><string>rss</string></value></param>'.
	'<param><value><string>'.$startAt.'</string></value></param>'.
	'<param><value><string>'.$interval.'</string></value></param>'.
	'<param><value><string>execute={sh,-c,'.$php.' '.$rootPath.'/plugins/rss/update.php'.'&amp; exit 0}</string></value></param>'.
	'</params>'.
	'</methodCall>');
require_once($rootPath.'/plugins/rss/rss.php');
$mngr = new rRSSManager();
$mngr->setStartTime($startAt);
$theSettings->registerPlugin("rss");
?>
