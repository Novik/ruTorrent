<?php

if( count( $argv ) > 2 )
	$_SERVER['REMOTE_USER'] = $argv[2];

if(( count( $argv ) > 1 ) && chdir(dirname( __FILE__)))
{
	require_once( "check.php" );

	$hashes = unserialize(file_get_contents( $argv[1] ));
	if(is_array($hashes))
		foreach( $hashes as $hash )
			ruTrackerChecker::run($hash);
	@unlink($argv[1]);
}
