<?php

require_once( '../../xmlrpc.php' );
require_once( 'util_rt.php' );
require_once( 'conf.php' );

function Debug( $str )
{
        global $datadir_debug_enabled;
        if( $datadir_debug_enabled ) rtDbg( "SetDir: ".$str );
}

Debug( "" );
Debug( "--- begin ---" );

$is_ok = true;
umask( $datadir_umask );

if( count( $argv ) < 3 )
{
	Debug( "called without arguments (hash wanted)" );
	$is_ok = false;
}
else {
	$hash           = $argv[1];
	$datadir        = $argv[2];
	$move_datafiles = $argv[3];
}

if( $is_ok && $hash && $datadir != '' )
{
	if( $move_datafiles == '1' )
		Debug( $datadir.", move files" );
	else
		Debug( $datadir.", don't move files" );

	if( !is_dir( $datadir ) )
	{
		// recursive mkdir() only after PHP_5.0
		mkdir( $datadir, 0777, true );
		//system( 'mkdir -p "'.$datadir.'"' );
	}
	if( !is_dir( $datadir ) )
	{
		Debug( "no such directory: \"".$datadir."\"" );
	}
	elseif( !rtSetDataDir( $hash, $datadir, $move_datafiles == '1', $datadir_debug_enabled ) )
	{
		Debug( "rtSetDataDir() fail!" );
	}
}

Debug( "--- end ---" );

?>