<?php
require_once( 'throttle.php' );

$thr = new rThrottle();
$thr->set();
CachedEcho::send($thr->get(),"application/javascript");
