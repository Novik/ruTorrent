<?php
require_once( 'throttle.php' );

$thr = new rThrottle();
if(isset($_REQUEST['apply']))
{
	$thr->apply();
}
else
{
	$thr->set();
	CachedEcho::send($thr->get(),"application/javascript");
}
