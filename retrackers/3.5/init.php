<?php

require_once( '../plugins/retrackers/retrackers.php');

$req = new rXMLRPCRequest( array(
	$theSettings->getOnInsertCommand(array('tadd_trackers1'.getUser(), getCmd('d.set_custom4').'=$'.getCmd('cat').'=$'.getCmd('d.get_state='))),
	$theSettings->getOnInsertCommand(array('tadd_trackers2'.getUser(),
		getCmd('branch').'=$'.getCmd('not').'=$'.getCmd('d.get_custom3').'=,"'.getCmd('cat').'=$'.getCmd('d.stop').'=,\"$'.
			getCmd('execute').'={sh,'.$rootPath.'/plugins/retrackers/run.sh'.','.getPHP().',$'.getCmd('d.get_hash').'=,'.getUser().'}\"" ; '.getCmd('d.set_custom3=')))));
if($req->run() && !$req->fault)
{
	$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
	$trks = rRetrackers::load();
	$jResult.=$trks->get();
}
else
	$jResult .= "plugin.disable(); noty('retrackers: '+theUILang.pluginCantStart,'error');";
