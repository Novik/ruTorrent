<?php

if( !chdir( dirname( __FILE__) ) )
	exit();

# Script arguments are:
# 0: script name
# 1: hash
# 2: target datadir
# 3: flag, "1" means "add torrent's path"
# 4: flag, "1" means "move datafiles"
# 5: flag, "1" means "fast resume"
# 6: username
if( count( $argv ) > 6 )
	$_SERVER['REMOTE_USER'] = $argv[6];

require_once( '../../php/xmlrpc.php' );
require_once( './util_setdir.php' );
require_once( './util_rt.php' );
eval( getPluginConf( 'datadir' ) );

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

if( count( $argv ) < 6 )
{
	Debug( "called without arguments (at least 5 params wanted)" );
	$is_ok = false;
}
else {
	$hash            = trim( $argv[1] );
	$datadir         = trim( $argv[2] );
	$move_addpath    = trim( $argv[3] );
	$move_datafiles  = trim( $argv[4] );
	$move_fastresume = trim( $argv[5] );
}

if( $is_ok && $hash && strlen( $datadir ) > 0 )
{
	Debug( "hash        : ".$hash );
	Debug( "data dir    : ".$datadir );
	Debug( "add path    : ".$move_addpath );
	Debug( "move files  : ".$move_datafiles );
	Debug( "fast resume : ".$move_fastresume );

	if( !rtMkDir( $datadir, 0777 ) )
	{
		Debug( "can't create ".$datadir );
	}
	elseif( !rtSetDataDir( $hash, $datadir, 
		$move_addpath == '1', $move_datafiles == '1', $move_fastresume == '1',
		$datadir_debug_enabled ) )
	{
		Debug( "rtSetDataDir() fail!" );
	}
}

Debug( "--- end ---" );

rtSemUnlock( $DataDir_Sem );
