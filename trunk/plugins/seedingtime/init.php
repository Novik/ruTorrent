<?php

if( $theSettings->iVersion >= 0x806 )
{
	$req = new rXMLRPCRequest( array(
		$theSettings->getOnFinishedCommand(array("seedingtime".getUser(),'d.set_custom=seedingtime,"$execute_capture={date,+%s}"')),
		$theSettings->getOnInsertCommand(array("addtime".getUser(),'d.set_custom=addtime,"$execute_capture={date,+%s}"'))
		));
	if($req->success())
	        $theSettings->registerPlugin("seedingtime");
	else
	        $jResult .= "plugin.disable(); log('seedingtime: '+theUILang.pluginCantStart);";
}
else
	$jResult .= "plugin.disable(); plugin.showError('theUILang.seedingTimeBadVersion');";
?>
