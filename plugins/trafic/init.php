<?php
require_once( 'plugins/trafic/conf.php' );
require_once( 'util.php' );

if(DO_DIAGNOSTIC)
{
	if(!$pathToPHP || ($pathToPHP==""))
		findRemoteEXE('php',"utWebUI.showTrafError('WUILang.trafPHPNotFound');",$remoteRequests);
	@chmod($theSettings->path.'plugins/trafic/stats',0777);
	@chmod($theSettings->path.'plugins/trafic/stats/trackers',0777);
	if( (is_dir($theSettings->path.'plugins/trafic/stats') && !isUserHavePermission($theSettings->uid,$theSettings->gid,$theSettings->path.'plugins/trafic/stats',0x0007)) ||
	    (is_dir($theSettings->path.'plugins/trafic/stats/trackers') && !isUserHavePermission($theSettings->uid,$theSettings->gid,$theSettings->path.'plugins/trafic/stats/trackers',0x0007)))
		$jResult.="utWebUI.showTrafError('WUILang.trafStatsNotAvailable');";
	@chmod($theSettings->path.'plugins/trafic/update.php',0644);
	if(!isUserHavePermission($theSettings->uid,$theSettings->gid,$theSettings->path.'plugins/trafic/update.php',0x0004))
		$jResult.="utWebUI.showTrafError('WUILang.trafUpdaterNotAvailable');";
}
if($isAutoStart)
{
	$tm = getdate();
	$startAt = mktime($tm["hours"],
		((integer)($tm["minutes"]/$updateInterval))*$updateInterval+$updateInterval-1,
		0,$tm["mon"],$tm["mday"],$tm["year"])-$tm[0];
	$interval = $updateInterval*60;
	if(!$pathToPHP || ($pathToPHP==""))
		$pathToPHP = "php";
	send2RPC('<?xml version="1.0" encoding="UTF-8"?>'.
		'<methodCall>'.
		'<methodName>schedule</methodName>'.
		'<params>'.
		'<param><value><string>trafic</string></value></param>'.
		'<param><value><string>'.$startAt.'</string></value></param>'.
		'<param><value><string>'.$interval.'</string></value></param>'.
		'<param><value><string>execute={sh,-c,'.$pathToPHP.' '.$theSettings->path.'plugins/trafic/update.php'.'&amp; exit 0}</string></value></param>'.
		'</params>'.
		'</methodCall>');
}
$theSettings->registerPlugin("trafic");
?>
