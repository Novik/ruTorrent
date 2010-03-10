<?php
set_time_limit(0);
$path = dirname(realpath($argv[0]));
if(chdir($path))
{
	if( count( $argv ) > 1 )
		$_SERVER['REMOTE_USER'] = $argv[1];
	require_once('rss.php');
	$mngr = new rRSSManager();
	$mngr->update();
}
?>
