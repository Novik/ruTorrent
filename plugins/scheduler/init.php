<?php
require_once( 'plugins/scheduler/conf.php' );
require_once( 'plugins/scheduler/scheduler.php' );
require_once( 'util.php' );

if(DO_DIAGNOSTIC)
{
	if(!$pathToPHP || ($pathToPHP==""))
		findRemoteEXE('php',"utWebUI.showSchedulerError('WUILang.schedPHPNotFound');",$remoteRequests);
	@chmod($theSettings->path.'plugins/scheduler/update.php',0644);
	if(!isUserHavePermission($theSettings->uid,$theSettings->gid,$theSettings->path.'plugins/scheduler/update.php',0x0004))
		$jResult.="utWebUI.showTrafError('WUILang.schedUpdaterNotAvailable');";
}
$schd = rScheduler::load();
if($isAutoStart)
{
	$schd->apply();
	$tm = getdate();
	$startAt = mktime($tm["hours"],
		((integer)($tm["minutes"]/$updateInterval))*$updateInterval+$updateInterval+1,
		0,$tm["mon"],$tm["mday"],$tm["year"])-$tm[0];
	$interval = $updateInterval*60;
	if(!$pathToPHP || ($pathToPHP==""))
		$pathToPHP = "php";
	send2RPC('<?xml version="1.0" encoding="UTF-8"?>'.
		'<methodCall>'.
		'<methodName>schedule</methodName>'.
		'<params>'.
		'<param><value><string>scheduler</string></value></param>'.
		'<param><value><string>'.$startAt.'</string></value></param>'.
		'<param><value><string>'.$interval.'</string></value></param>'.
		'<param><value><string>execute={sh,-c,'.$pathToPHP.' '.$theSettings->path.'plugins/scheduler/update.php'.'&amp; exit 0}</string></value></param>'.
		'</params>'.
		'</methodCall>');
}
$theSettings->registerPlugin("scheduler");
$jResult.=$schd->get();
?>
