<?php
require_once( '../plugins/throttle/throttle.php');

$thr = rThrottle::load();
if(!$thr->obtain())
	$jResult.="plugin.disable(); noty('throttle: '+theUILang.pluginCantStart,'error');";
else
	$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
$jResult.=$thr->get();
