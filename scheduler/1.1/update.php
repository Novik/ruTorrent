<?php
$path = dirname(realpath($argv[0]));
if(chdir($path))
{
	require_once('scheduler.php');
	$sch = rScheduler::load();
	$sch->apply();
}
?>
