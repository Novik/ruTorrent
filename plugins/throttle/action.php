<?php
require_once( 'throttle.php' );

$thr = new rThrottle();
$thr->set();
header("Content-Type: application/javascript; charset=UTF-8");
cachedEcho($thr->get());

?>
