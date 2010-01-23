<?php

if( $theSettings->iVersion >= 0x805 )
{
	$req = new rXMLRPCRequest( new rXMLRPCCommand( "system.method.set_key", 
		array("event.download.finished","seedingtime".getUser(),'d.set_custom=seedingtime,"$execute_capture={date,+%s}"') ) );
	if($req->run() && !$req->fault)
	        $theSettings->registerPlugin("seedingtime");
	else
	        $jResult .= "plugin.disable(); log('seedingtime: '+theUILang.pluginCantStart);";
}
else
	$jResult .= "plugin.disable(); plugin.showError('theUILang.seedingTimeBadVersion');";
?>
