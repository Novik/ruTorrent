<?php

require_once( '../../php/util.php' );
require_once( '../../php/xmlrpc.php' );
require_once( './util_setdir.php' );
require_once( './util_rt.php' );
require_once( './setdircommand.php' );
eval( FileUtil::getPluginConf( 'datadir' ) );

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
			if( !ctype_xdigit($hash) )
			{
				$hash = null;
			}
		}
		else if( $parts[0] == "datadir" )
		{
			$datadir = trim( rawurldecode( $parts[1] ) );
		}
		else if($parts[0]=="move_addpath")
		{
			$move_addpath = intval( $parts[1] );
		}
		else if( $parts[0] == "move_datafiles" )
		{
			$move_datafiles = intval( $parts[1] );
		}
		else if( $parts[0] == "move_fastresume" )
		{
			$move_fastresume = intval( $parts[1] );
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
		$php = Utility::getPHP();
		Debug( "script dir  : ".$script_dir );
		Debug( "path to php : ".$php );
		Debug( "hash        : ".$hash );
		Debug( "data dir    : ".$datadir );
		Debug( "add path    : ".$move_addpath );
		Debug( "move files  : ".$move_datafiles );
		Debug( "fast resume : ".$move_fastresume );
		// The move runs in a process of its own, started through rTorrent and
		// detached: the shell line ends "& exit 0", so what comes back below is
		// that the job started and never that it worked. A destination that is
		// already taken is the one refusal that does not need the move to have
		// started -- it reads the destination and the file list, nothing else --
		// so it is answered here, where the dialog is still listening.
		//
		// It is the same test the move applies, through the same function, so a
		// name the move would have written is not refused here.
		$taken = $move_datafiles
			? rtDataDirCollision( $hash, $datadir, $move_addpath, $datadir_debug_enabled )
			: '';
		if( $taken != '' )
		{
			Debug( "refused, destination already exists: ".$taken );
			$errors[] = array('desc'=>"theUILang.datadirSetDirFail", 'prm'=>$taken);
		}
		else
			$res = rtExec( "execute",
				array( "sh",
					"-c",
					rtSetDirCommand( $php, $script_dir."setdir.php", $hash, $datadir,
						$move_addpath, $move_datafiles, $move_fastresume, User::getUser() ),
				),
				$datadir_debug_enabled );
	}

	if( !$res && !count( $errors ) )
	{
		$errors[] = array('desc'=>"theUILang.datadirSetDirFail", 'prm'=>$datadir);
	}
}

Debug( "--- end ---" );

CachedEcho::send(JSON::safeEncode(array( "errors"=>$errors )),"application/json");
