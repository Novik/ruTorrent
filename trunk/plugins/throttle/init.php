<?php
require_once( '../plugins/throttle/throttle.php');

$thr = rThrottle::load();
if(!$thr->obtain())
	$jResult.="plugin.disable(); log('throttle: '+theUILang.pluginCantStart);";
else
	$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
$jResult.=$thr->get();

?>