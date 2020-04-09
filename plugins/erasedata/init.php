<?php

require_once( 'xmlrpc.php' );
eval(getPluginConf($plugin["name"]));

$listPath = getSettingsPath()."/erasedata";
@makeDirectory($listPath);
$thisDir = dirname(__FILE__);

$req = new rXMLRPCRequest( array(
	$theSettings->getOnEraseCommand(array('erasedata0'.getUser(),
		getCmd('d.open').'= ; '.getCmd('branch=').getCmd('d.get_custom5').'=,"'.
			getCmd('f.multicall').'=,\"'.getCmd('file.append').'=(cat,'.$listPath.'/,$'.getCmd('system.pid').'=,.tmp),$'.getCmd('f.get_frozen_path').'=\""')),
	$theSettings->getOnEraseCommand(array('erasedata1'.getUser(),
		getCmd('branch=').getCmd('d.get_custom5').'=,"'.
			getCmd('execute').'={'.$thisDir.'/fin.sh,'.
				$listPath.',$'.
				getCmd('system.pid').'=,$'.
				getCmd('d.get_hash').'=,$'.
				getCmd('d.get_base_path').'=,$'.
				getCmd('d.is_multi_file').'=,$'.
				getCmd('d.get_custom5').'=}"')),
	$theSettings->getAbsScheduleCommand("erasedata",$garbageCheckInterval,
		getCmd('execute').'={sh,-c,'.escapeshellarg(getPHP()).' '.escapeshellarg($thisDir.'/update.php').' '.escapeshellarg(getUser()).' &}' )
	) );
if($req->success())
{
	$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
	$jResult.="plugin.enableForceDeletion = ".($enableForceDeletion ? 1 : 0).";";
}
else
	$jResult.="plugin.disable(); noty('erasedata: '+theUILang.pluginCantStart,'error');";
