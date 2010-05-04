<?php

if( !chdir( dirname( __FILE__) ) )
	exit;

if( count( $argv ) > 1 )
{
	$usr = ($argv[1]=='--daemon') ? 3 : 2;
	if( count( $argv ) > $usr )
		$_SERVER['REMOTE_USER'] = $argv[$usr];
}

require_once( "./util_rt.php" );
require_once( "./autotools.php" );
eval( getPluginConf( 'autotools' ) );

// If we are not in background, run this script in background
array_shift( $argv );
if( !rtIsDaemon( $argv ) )
{
	rtDaemon( getPHP(), basename( __FILE__ ), $argv );
	// script was exited at the line above
}

// arguments array was shifted and  "--daemon" param was added, so:
// 0: --daemon
// 1: hash
// 2: username

$AutoLabel_Sem = rtSemGet( fileinode( __FILE__ ) );
rtSemLock( $AutoLabel_Sem );

function Debug( $str )
{
	global $autodebug_enabled;
	if( $autodebug_enabled ) rtDbg( "AutoLabel", $str );
}

Debug( "" );
Debug( "--- begin ---" );

$is_ok = true;
if( count( $argv ) < 2 )
{
	Debug( "called without arguments (hash wanted)" );
	$is_ok = false;
}
else $hash = $argv[1];

// Read configuration
if( $is_ok )
{
	$at = rAutoTools::load();
	Debug( "enabled         : ".$at->enable_label );
	if( !$at->enable_label )
		$is_ok = false;
}

// Get info from rTorrent
if( $is_ok )
{
	$req = new rXMLRPCRequest( array (
		new rXMLRPCCommand( "d.get_directory", $hash ),
		new rXMLRPCCommand( "d.get_custom3",   $hash ),
		new rXMLRPCCommand( "d.is_multi_file", $hash ),
	) );
	if( $req->run() && !$req->fault )
	{
		$is_multy_file = ( $req->val[2] != 0 );
		if(!isset($theSettings))
			$theSettings = rTorrentSettings::load();
		$default_dir = $theSettings->directory;
		$torrent_dir = trim( $req->val[0] );
		$custom3     = trim( $req->val[1] );
		Debug( "get_directory   : ".$default_dir );
		Debug( "d.get_directory : ".$torrent_dir );
		Debug( "d.get_custom3   : ".$custom3 );
		Debug( "d.is_multy_file : ".$is_multy_file );
		if( $default_dir == '' || $torrent_dir == '' )
		{
			Debug( "base paths are not set" );
			$is_ok = false;
		}
		elseif( $custom3 == '1' )
		{
			Debug( "torrent is NOT NEW (modified by another plugin)" );
			$is_ok = false;
		}
	}
	else {
		Debug( "rXMLRPCRequest() fail" );
		$is_ok = false;
	}
}

// Set a label
if( $is_ok )
{
	if( $is_multy_file )
		$torrent_dir = rtRemoveLastToken( $torrent_dir, '/' );
	$label = rtGetRelativePath( $default_dir, $torrent_dir );
	Debug( "label           : \"".$label."\"" );
	if( $label != '' && $label != './' )
		rtExec( "d.set_custom1", array( $hash, rawurlencode( $label ) ), $autodebug_enabled );
}

Debug( "--- end ---" );

rtSemUnlock( $AutoLabel_Sem );

?>
