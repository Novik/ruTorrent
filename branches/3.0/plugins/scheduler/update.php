<?php
$path = dirname(realpath($argv[0]));
if(chdir($path))
{
	$_SERVER['REMOTE_USER'] = $argv[1];
	require_once('scheduler.php');
	$sch = rScheduler::load();
	$sch->apply();
}
?>
