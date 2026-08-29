<?php
require_once( dirname(__FILE__)."/throttle.php");

$thr = rThrottle::load();
if(!$thr->obtain())
	$jResult.="plugin.disable(); noty('throttle: '+theUILang.pluginCantStart,'error');";
else
	$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
$jResult.=$thr->get();
