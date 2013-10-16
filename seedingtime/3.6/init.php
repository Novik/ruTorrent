<?php

$req = new rXMLRPCRequest( array(
	$theSettings->getOnFinishedCommand(array("seedingtime".getUser(),
		getCmd('d.set_custom').'=seedingtime,"$'.getCmd('execute_capture').'={date,+%s}"')),
	$theSettings->getOnInsertCommand(array("addtime".getUser(),
		getCmd('d.set_custom').'=addtime,"$'.getCmd('execute_capture').'={date,+%s}"')),

	$theSettings->getOnHashdoneCommand(array("seedingtimecheck".getUser(),
		getCmd('branch=').'$'.getCmd('not=').'$'.getCmd('d.get_complete=').',,'.
		getCmd('d.get_custom').'=seedingtime,,"'.getCmd('d.set_custom').'=seedingtime,$'.getCmd('d.get_custom').'=addtime'.'"')),
	));
if($req->success())
        $theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
else
        $jResult .= "plugin.disable(); noty('seedingtime: '+theUILang.pluginCantStart,'error');";
