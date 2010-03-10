<?php

if( $theSettings->iVersion >= 0x806 )
{
	$req = new rXMLRPCRequest( array(
	        new rXMLRPCCommand( "system.method.set_key", 
			array("event.download.finished","seedingtime".getUser(),'d.set_custom=seedingtime,"$execute_capture={date,+%s}"') ),
	        new rXMLRPCCommand( "system.method.set_key", 
			array("event.download.inserted_new","addtime".getUser(),'d.set_custom=addtime,"$execute_capture={date,+%s}"') )
		));
	if($req->success())
	        $theSettings->registerPlugin("seedingtime");
	else
	        $jResult .= "plugin.disable(); log('seedingtime: '+theUILang.pluginCantStart);";
}
else
	$jResult .= "plugin.disable(); plugin.showError('theUILang.seedingTimeBadVersion');";
?>
