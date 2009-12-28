<?php
require_once( 'util.php' );
require_once( '../plugins/trafic/conf.php' );

$st = getSettingsPath();
@rename($rootPath.'/plugins/trafic/stats',$st.'/trafic');
@mkdir($st.'/trafic');
@mkdir($st.'/trafic/trackers');
if($do_diagnostic)
{
	if(!$pathToPHP || ($pathToPHP==""))
		findRemoteEXE('php',"thePlugins.get('trafic').showError('theUILang.trafPHPNotFound');",$remoteRequests);
	@chmod($st.'/trafic',0777);
	@chmod($st.'/trafic/trackers',0777);
	if( (is_dir($st.'/trafic') && !isUserHavePermission($theSettings->uid,$theSettings->gid,$st.'/trafic',0x0007)) ||
	    (is_dir($st.'/trafic/trackers') && !isUserHavePermission($theSettings->uid,$theSettings->gid,$st.'/trafic/trackers',0x0007)))
		$jEnd.="plugin.showError('theUILang.trafStatsNotAvailable');";
	@chmod($rootPath.'/plugins/trafic/update.php',0644);
	if(!isUserHavePermission($theSettings->uid,$theSettings->gid,$rootPath.'/plugins/trafic/update.php',0x0004))
		$jEnd.="plugin.showError('theUILang.trafUpdaterNotAvailable');";
}
$tm = getdate();
$startAt = mktime($tm["hours"],
	((integer)($tm["minutes"]/$updateInterval))*$updateInterval+$updateInterval-1,
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
	'<param><value><string>trafic</string></value></param>'.
	'<param><value><string>'.$startAt.'</string></value></param>'.
	'<param><value><string>'.$interval.'</string></value></param>'.
	'<param><value><string>execute={sh,-c,'.$php.' '.$rootPath.'/plugins/trafic/update.php'.'&amp; exit 0}</string></value></param>'.
	'</params>'.
	'</methodCall>');
$theSettings->registerPlugin("trafic");
?>
