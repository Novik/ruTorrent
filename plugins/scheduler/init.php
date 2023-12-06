<?php

require_once( '../plugins/scheduler/scheduler.php' );

$schd = rScheduler::load();
$schd->apply();

$req = new rXMLRPCRequest( $theSettings->getAbsScheduleCommand('scheduler',$updateInterval*60,
	getCmd('execute').'={sh,-c,'.escapeshellarg(Utility::getPHP()).' '.escapeshellarg($rootPath.'/plugins/scheduler/update.php').' '.escapeshellarg(User::getUser()).' & exit 0}' ) );
if($req->run() && !$req->fault)
{
	$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
	$jResult.=$schd->get();
}
else
	$jResult.="plugin.disable(); noty('scheduler: '+theUILang.pluginCantStart,'error');";
