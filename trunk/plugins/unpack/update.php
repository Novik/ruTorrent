<?php

if( chdir( dirname( __FILE__) ) )
{
	set_time_limit(0);
	if( count( $argv ) > 4 )
		$_SERVER['REMOTE_USER'] = $argv[4];
	if( count( $argv ) > 3 )
	{
		require_once( "unpack.php" );
		$up = rUnpack::load();
		if($up->enabled)
			$up->startSilentTask($argv[1],rawurldecode($argv[2]),$argv[3]);
	}
}

?>
