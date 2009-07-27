<?php
require_once( 'plugins/rss/rssconf.php' );
require_once( 'util.php' );

if(DO_DIAGNOSTIC)
{
	if(!$pathToPHP || ($pathToPHP==""))
		findRemoteEXE('php',"utWebUI.showRSSError('WUILang.rssPHPNotFound');",$remoteRequests);
	if(!$pathToCurl || ($pathToCurl==""))
	{
		findRemoteEXE('curl',"utWebUI.showRSSError('WUILang.rssCurlNotFound');",$remoteRequests);
		if(findEXE('curl')==false)
			$jResult.="utWebUI.showRSSError('WUILang.rssCurlNotFound1');";
	}
	else
		if(!is_executable($pathToCurl))
			$jResult.="utWebUI.showRSSError('WUILang.rssCurlNotFound1');";
	@chmod($theSettings->path.'plugins/rss/cache',0777);
	if(is_dir($theSettings->path.'plugins/rss/cache') && !isUserHavePermission($theSettings->uid,$theSettings->gid,$theSettings->path.'plugins/rss/cache',0x0007))
		$jResult.="utWebUI.showRSSError('WUILang.rssCacheNotAvailable');";
	@chmod($theSettings->path.'plugins/rss/update.php',0644);
	if(!isUserHavePermission($theSettings->uid,$theSettings->gid,$theSettings->path.'plugins/rss/update.php',0x0004))
		$jResult.="utWebUI.showRSSError('WUILang.rssUpdaterNotAvailable');";
}
if($isAutoStart)
{
	$tm = getdate();
	$startAt = mktime($tm["hours"],
		((integer)($tm["minutes"]/$updateInterval))*$updateInterval+$updateInterval,
		0,$tm["mon"],$tm["mday"],$tm["year"])-$tm[0];
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
		'<param><value><string>execute={sh,-c,'.$php.' '.$theSettings->path.'plugins/rss/update.php'.'&amp; exit 0}</string></value></param>'.
		'</params>'.
		'</methodCall>');
	$dir = getcwd();
	chdir('plugins/rss/');
	require_once('rss.php');
	$mngr = new rRSSManager();
	$mngr->setStartTime($startAt);
	chdir($dir);
}
$theSettings->registerPlugin("rss");
?>
