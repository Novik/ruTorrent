<?php

require_once( '../../php/util.php' );
require_once( '../../php/xmlrpc.php' );
require_once( './util_setdir.php' );
require_once( './util_rt.php' );
eval( getPluginConf( 'datadir' ) );

function Debug( $str )
{
	global $datadir_debug_enabled;
	if( $datadir_debug_enabled ) rtDbg( "DataDir", $str );
}

ignore_user_abort( true );
set_time_limit( 0 );

$errors = array();

if( !isset( $HTTP_RAW_POST_DATA ) )
	$HTTP_RAW_POST_DATA = file_get_contents( "php://input" );
if( isset( $HTTP_RAW_POST_DATA ) )
{
	$vars = explode( '&', $HTTP_RAW_POST_DATA );
	$hash = null;
	$datadir = "";
	$move_addpath = "1";
	$move_datafiles = "0";
	$move_fastresume = "1";
	foreach( $vars as $var )
	{
		$parts = explode( "=", $var );
		if( $parts[0] == "hash" )
		{
			$hash = trim( $parts[1] );
		}
		else if( $parts[0] == "datadir" )
		{
			$datadir = trim( rawurldecode( $parts[1] ) );
		}
		else if($parts[0]=="move_addpath")
		{
			$move_addpath = trim( $parts[1] );
		}
		else if( $parts[0] == "move_datafiles" )
		{
			$move_datafiles = trim( $parts[1] );
		}
		else if( $parts[0] == "move_fastresume" )
		{
			$move_fastresume = trim( $parts[1] );
		}
	}

	if(!rTorrentSettings::get()->correctDirectory($datadir))
	{
		$datadir = '';
	}

	Debug( "" );
	Debug( "--- begin ---" );
	Debug( $datadir );
	Debug( 
		"\"".($move_addpath    == '0' ? "don't " : "")."add path\"".
		", \"".($move_datafiles  == '0' ? "don't " : "")."move files\"".
		", \"".($move_fastresume == '0' ? "don't " : "")."fast resume\"" );

	$res = false;

	if( $hash && strlen( $datadir ) > 0 )
	{
		$script_dir = rtAddTailSlash( dirname( __FILE__ ) );
		$php = getPHP();
		Debug( "script dir  : ".$script_dir );
		Debug( "path to php : ".$php );
		Debug( "hash        : ".$hash );
		Debug( "data dir    : ".$datadir );
		Debug( "add path    : ".$move_addpath );
		Debug( "move files  : ".$move_datafiles );
		Debug( "fast resume : ".$move_fastresume );
		$res = rtExec( "execute",
			array( "sh",
				"-c",
				escapeshellarg($php)." ".escapeshellarg($script_dir."setdir.php").
					" ".$hash." ".escapeshellarg($datadir).
					" ".$move_addpath." ".$move_datafiles." ".$move_fastresume.
					" ".escapeshellarg(getUser())." & exit 0",
			),
			$datadir_debug_enabled );
	}

	if( !$res )
	{
		$errors[] = array('desc'=>"theUILang.datadirSetDirFail", 'prm'=>$datadir);
	}
}

Debug( "--- end ---" );

cachedEcho(safe_json_encode(array( "errors"=>$errors )),"application/json");
