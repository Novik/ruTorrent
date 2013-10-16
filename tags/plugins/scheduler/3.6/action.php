<?php
require_once( 'scheduler.php' );

$sch = rScheduler::load();
$sch->set();
cachedEcho($sch->get(),"application/javascript");
