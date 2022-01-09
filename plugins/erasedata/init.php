<?php

require_once( 'xmlrpc.php' );
eval(FileUtil::getPluginConf($plugin["name"]));

$listPath = FileUtil::getSettingsPath()."/erasedata";
@FileUtil::makeDirectory($listPath);
$thisDir = dirname(__FILE__);

$req = new rXMLRPCRequest( array(
	$theSettings->getOnEraseCommand(array('erasedata0'.User::getUser(),
		getCmd('d.open').'= ; '.getCmd('branch=').getCmd('d.get_custom5').'=,"'.
			getCmd('f.multicall').'=,\"'.getCmd('file.append').'=(cat,'.$listPath.'/,$'.getCmd('system.pid').'=,.tmp),$'.getCmd('f.get_frozen_path').'=\""')),
	$theSettings->getOnEraseCommand(array('erasedata1'.User::getUser(),
		getCmd('branch=').getCmd('d.get_custom5').'=,"'.
			getCmd('execute').'={'.$thisDir.'/fin.sh,'.
				$listPath.',$'.
				getCmd('system.pid').'=,$'.
				getCmd('d.get_hash').'=,$'.
				getCmd('d.get_base_path').'=,$'.
				getCmd('d.is_multi_file').'=,$'.
				getCmd('d.get_custom5').'=}"')),
	$theSettings->getAbsScheduleCommand("erasedata",$garbageCheckInterval,
		getCmd('execute').'={sh,-c,'.escapeshellarg(Utility::getPHP()).' '.escapeshellarg($thisDir.'/update.php').' '.escapeshellarg(User::getUser()).' &}' )
	) );
if($req->success())
{
	$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
	$jResult.="plugin.enableForceDeletion = ".($enableForceDeletion ? 1 : 0).";";
}
else
	$jResult.="plugin.disable(); noty('erasedata: '+theUILang.pluginCantStart,'error');";
