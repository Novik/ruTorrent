<?php
require_once( 'util.php' );
require_once( '../plugins/retrackers/retrackers.php');

if($do_diagnostic)
{
	if(!$pathToPHP || ($pathToPHP==""))
		findRemoteEXE('php',"thePlugins.get('retrackers').showError('WUILang.retrackersPHPNotFound');",$remoteRequests);
	@chmod($rootPath.'/plugins/retrackers/run.sh',0755);
	@chmod($rootPath.'/plugins/retrackers/update.php',0644);
	if(!isUserHavePermission($theSettings->uid,$theSettings->gid,$rootPath.'/plugins/retrackers/run.sh',0x0005))
		$jEnd.="plugin.showError('WUILang.retrackersRunNotAvailable');";
	if(!isUserHavePermission($theSettings->uid,$theSettings->gid,$rootPath.'/plugins/retrackers/update.php',0x0004))
		$jEnd.="plugin.showError('WUILang.retrackersUpdaterNotAvailable');";
}

if($theSettings->iVersion<0x804)
	$s = 'on_insert</methodName><params>';
else
	$s = 'system.method.set_key</methodName><params><param><value><string>event.download.inserted_new</string></value></param>';
if(!$pathToPHP || ($pathToPHP==""))
	$php = "php";
else
	$php = $pathToPHP;
send2RPC('<?xml version="1.0" encoding="UTF-8"?>'.
	'<methodCall><methodName>'.$s.
	'<param><value><string>add_trackers</string></value></param>'.
	'<param><value><string>branch=$not=$d.get_custom3=,"execute={'.$rootPath.'/plugins/retrackers/run.sh'.','.$php.',$d.get_hash=}" ; d.set_custom3=</string></value></param>'.
	'</params>'.
	'</methodCall>');
$theSettings->registerPlugin("retrackers");
$trks = rRetrackers::load();
$jResult.=$trks->get();
?>
