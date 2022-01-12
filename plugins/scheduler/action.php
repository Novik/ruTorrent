<?php
require_once( 'scheduler.php' );

$sch = rScheduler::load();
$sch->set();
CachedEcho::send($sch->get(),"application/javascript");
