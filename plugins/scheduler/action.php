<?php
require_once( 'scheduler.php' );

$sch = rScheduler::load();
$sch->set();
header("Content-Type: application/javascript; charset=UTF-8");
cachedEcho($sch->get());
?>
