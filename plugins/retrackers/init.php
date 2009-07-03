<?php
require_once( 'util.php' );
require_once( 'plugins/retrackers/retrackers.php');
require_once( 'plugins/retrackers/conf.php');

if(!$pathToPHP || ($pathToPHP==""))
	$pathToPHP = "php";

if(DO_DIAGNOSTIC)
{
	if(!$pathToPHP || ($pathToPHP==""))
		findRemoteEXE('php',"utWebUI.showRetrackersError('WUILang.retrackersPHPNotFound');",$remoteRequests);
	@chmod($theSettings->path.'plugins/retrackers/run.sh',0755);
	@chmod($theSettings->path.'plugins/retrackers/update.php',0644);
	if(!isUserHavePermission($theSettings->uid,$theSettings->gid,$theSettings->path.'plugins/retrackers/run.sh',0x0005))
		$jResult.="utWebUI.showRetrackersError('WUILang.retrackersRunNotAvailable');";
	if(!isUserHavePermission($theSettings->uid,$theSettings->gid,$theSettings->path.'plugins/retrackers/update.php',0x0004))
		$jResult.="utWebUI.showRetrackersError('WUILang.retrackersUpdaterNotAvailable');";
}
if($isAutoStart)
{
	if($theSettings->iVersion<0x804)
		$s = 'on_insert</methodName><params>';
	else
		$s = 'system.method.set_key</methodName><params><param><value><string>event.download.inserted_new</string></value></param>';
	send2RPC('<?xml version="1.0" encoding="UTF-8"?>'.
		'<methodCall><methodName>'.$s.
		'<param><value><string>add_trackers</string></value></param>'.
		'<param><value><string>branch=$not=$d.get_custom3=,"execute={'.$theSettings->path.'plugins/retrackers/run.sh'.','.$pathToPHP.',$d.get_hash=}" ; d.set_custom3=</string></value></param>'.
		'</params>'.
		'</methodCall>');
}
$theSettings->registerPlugin("retrackers");
$trks = rRetrackers::load();
$jResult.=$trks->get();
?>
