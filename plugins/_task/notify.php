<?php

$path = dirname(realpath($argv[0]));
if(chdir($path) && ( count( $argv ) > 3 ) )
{
	$_SERVER['REMOTE_USER'] = $argv[3];
	require_once( 'task.php' );

        rTask::notify( $argv[2], $argv[1] ? 'TaskFail' : 'TaskSuccess' );
}
