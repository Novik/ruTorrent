<?php

require_once( 'xmlrpc.php' );
eval(getPluginConf('erasedata'));

$listPath = getSettingsPath()."/erasedata";
@makeDirectory($listPath);
$thisDir = dirname(__FILE__);

$req = new rXMLRPCRequest( array(
	$theSettings->getOnEraseCommand(array('erasedata0'.getUser(),
		getCmd('branch').'=$'.getCmd('not').'=$'.getCmd('d.get_custom5').'=,,$'.getCmd('cat').'=$'.getCmd('d.open').'=,'.
			'"$'.getCmd('f.multicall').'=,\"'.getCmd('execute').'={'.$thisDir.'/cat.sh,'.$listPath.',$'.getCmd('system.pid').'=,$'.getCmd('f.get_frozen_path').'=}\"",'.
			'"$'.getCmd('execute').'={'.$thisDir.'/fin.sh,'.$listPath.',$'.getCmd('system.pid').'=,$'.getCmd('d.get_hash').'=,$'.getCmd('d.get_base_path').'=,$'.getCmd('d.is_multi_file').'=}"'
		)),
	new rXMLRPCCommand('schedule', array( 'erasedata'.getUser(), '5', $garbageCheckInterval."", 
		getCmd('execute').'={sh,-c,'.escapeshellarg(getPHP()).' '.escapeshellarg($thisDir.'/update.php').' '.escapeshellarg(getUser()).' &}' ))
	));
if($req->success())
	$theSettings->registerPlugin( "erasedata" );
else
	$jResult.="plugin.disable(); log('erasedata: '+theUILang.pluginCantStart);";

?>