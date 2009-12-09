<?php

if( !chdir( dirname( __FILE__) ) )
	exit;
require_once( '../../xmlrpc.php' );
require_once( 'util_rt.php' );
require_once( 'conf.php' );

$DataDir_Sem = rtSemGet( fileinode( __FILE__ ) );
rtSemLock( $DataDir_Sem );

function Debug( $str )
{
	global $datadir_debug_enabled;
	if( $datadir_debug_enabled ) rtDbg( "SetDir", $str );
}

Debug( "" );
Debug( "--- begin ---" );

$is_ok = true;
umask( $datadir_umask );

if( count( $argv ) < 4 )
{
	Debug( "called without arguments (3 params wanted)" );
	$is_ok = false;
}
else {
	$hash           = trim( $argv[1] );
	$datadir        = trim( $argv[2] );
	$move_datafiles = trim( $argv[3] );
}

if( $is_ok && $hash && strlen( $datadir ) > 0 )
{
	Debug( "hash        : ".$hash );
	Debug( "data dir    : ".$datadir );
	Debug( "move files  : ".$move_datafiles );

	if( !rtMkDir( $datadir, 0777 ) )
	{
		Debug( "can't create ".$datadir );
	}
	elseif( !rtSetDataDir( $hash, $datadir, $move_datafiles == '1', $datadir_debug_enabled ) )
	{
		Debug( "rtSetDataDir() fail!" );
	}
}

Debug( "--- end ---" );

rtSemUnlock( $DataDir_Sem );

?>