<?php

require_once( "xmlrpc.php" );
eval( getPluginConf( 'rutracker_check' ) );

if($updateInterval)
{
	$tm = getdate();
	$startAt = mktime($tm["hours"],
		((integer)($tm["minutes"]/$updateInterval))*$updateInterval+$updateInterval,
		0,$tm["mon"],$tm["mday"],$tm["year"])-$tm[0];
	if($startAt<0)
		$startAt = 0;
	$interval = $updateInterval*60;

	$req = new rXMLRPCRequest( new rXMLRPCCommand('schedule', array( 'rutracker_check'.getUser(), $startAt."", $interval."", 
		getCmd('execute').'={sh,-c,'.escapeshellarg(getPHP()).' '.escapeshellarg(dirname(__FILE__).'/update.php').' '.escapeshellarg(getUser()).' &}' )));
	if($req->success())
		$theSettings->registerPlugin("rutracker_check");
	else
		$jResult .= "plugin.disable(); log('rutracker_check: '+theUILang.pluginCantStart);";
}
else
{
	require( 'done.php' );
	$theSettings->registerPlugin("rutracker_check");
}

?>