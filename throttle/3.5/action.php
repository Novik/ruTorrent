<?php
require_once( 'throttle.php' );

$thr = new rThrottle();
$thr->set();
cachedEcho($thr->get(),"application/javascript");
