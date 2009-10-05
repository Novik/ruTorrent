<?php
require_once( 'plugins/throttle/throttle.php');

$thr = rThrottle::load();
if(($theSettings->iVersion<0x804) || !$thr->obtain())
	$jResult.="utWebUI.showThrottleError('WUILang.throttleUnsupported'); utWebUI.throttleSupported = false; ";
else
{
	$jResult.="utWebUI.trtColumns.push({'text' : 'Channel','width' : '60px','type' : TYPE_STRING}); ";
	$theSettings->registerPlugin("throttle");
}
$jResult.=$thr->get();

?>
