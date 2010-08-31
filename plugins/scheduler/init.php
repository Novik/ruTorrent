<?php

require_once( '../plugins/scheduler/scheduler.php' );

$schd = rScheduler::load();
$schd->apply();
$tm = getdate();
$startAt = mktime($tm["hours"],
	((integer)($tm["minutes"]/$updateInterval))*$updateInterval+$updateInterval+1,
	0,$tm["mon"],$tm["mday"],$tm["year"])-$tm[0];
$interval = $updateInterval*60;

$req = new rXMLRPCRequest( new rXMLRPCCommand('schedule', array( "scheduler".getUser(), $startAt.'', $interval.'', 
	getCmd('execute').'={sh,-c,'.escapeshellarg(getPHP()).' '.escapeshellarg($rootPath.'/plugins/scheduler/update.php').' '.escapeshellarg(getUser()).' & exit 0}' )) );
if($req->run() && !$req->fault)
{
	$theSettings->registerPlugin("scheduler");
	$jResult.=$schd->get();
}
else
	$jResult.="plugin.disable(); log('scheduler: '+theUILang.pluginCantStart);";

?>