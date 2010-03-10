<?php
require_once( '../plugins/throttle/throttle.php');

$thr = rThrottle::load();
if(($theSettings->iVersion<0x804) || !$thr->obtain())
	$jResult.="plugin.disable();";
else
	$theSettings->registerPlugin("throttle");
$jResult.=$thr->get();

?>
