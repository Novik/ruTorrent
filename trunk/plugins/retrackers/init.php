<?php

require_once( '../plugins/retrackers/retrackers.php');

$needStart = true;
if($do_diagnostic)
{
	findRemoteEXE('php',"thePlugins.get('retrackers').showError('theUILang.retrackersPHPNotFound');",$remoteRequests);
	@chmod($rootPath.'/plugins/retrackers/run.sh',0755);
	@chmod($rootPath.'/plugins/retrackers/update.php',0644);
	if(!isUserHavePermission($theSettings->uid,$theSettings->gid,$rootPath.'/plugins/retrackers/run.sh',0x0005))
	{
		$jResult.="plugin.disable(); plugin.showError('theUILang.retrackersRunNotAvailable');";
		$needStart = false;
	}
	if(!isUserHavePermission($theSettings->uid,$theSettings->gid,$rootPath.'/plugins/retrackers/update.php',0x0004))
	{
		$jResult.="plugin.disable(); plugin.showError('theUILang.retrackersUpdaterNotAvailable');";
		$needStart = false;
	}
}
if($needStart)
{
	$req = new rXMLRPCRequest( array(
		$theSettings->getOnInsertCommand(array('tadd_trackers1'.getUser(), getCmd('d.set_custom4').'=$'.getCmd('cat').'=$'.getCmd('d.get_state='))),
		$theSettings->getOnInsertCommand(array('tadd_trackers2'.getUser(),
			getCmd('branch').'=$'.getCmd('not').'=$'.getCmd('d.get_custom3').'=,"'.getCmd('cat').'=$'.getCmd('d.stop').'=,\"$'.
				getCmd('execute').'={sh,'.$rootPath.'/plugins/retrackers/run.sh'.','.getPHP().',$'.getCmd('d.get_hash').'=,'.getUser().'}\"" ; '.getCmd('d.set_custom3=')))));
	if($req->run() && !$req->fault)
	{
		$theSettings->registerPlugin("retrackers");
		$trks = rRetrackers::load();
		$jResult.=$trks->get();
	}
	else
		$jResult .= "plugin.disable(); log('retrackers: '+theUILang.pluginCantStart);";
}
?>
