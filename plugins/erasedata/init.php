<?php

require_once( 'xmlrpc.php' );
eval(FileUtil::getPluginConf($plugin["name"]));

$listPath = FileUtil::getSettingsPath()."/erasedata";
@FileUtil::makeDirectory($listPath);
$thisDir = dirname(__FILE__);

// The list of files to delete is written by the RPC handler (removewithdata)
// straight into $listPath, so the plugin only needs to schedule the garbage
// collector that applies and clears those lists.
$req = new rXMLRPCRequest( array(
	$theSettings->getAbsScheduleCommand("erasedata",$garbageCheckInterval,
		getCmd('execute').'={sh,-c,'.escapeshellarg(Utility::getPHP()).' '.escapeshellarg($thisDir.'/update.php').' '.escapeshellarg(User::getUser()).' &}' )
	) );
if($req->success())
{
	$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
	$jResult.="plugin.enableForceDeletion = ".($enableForceDeletion ? 1 : 0).";";
	$jResult.="plugin.replaceRemoveTorrent = ".($replaceRemoveTorrent ? 1 : 0).";";
}
else
	$jResult.="plugin.disable(); noty('erasedata: '+theUILang.pluginCantStart,'error');";
