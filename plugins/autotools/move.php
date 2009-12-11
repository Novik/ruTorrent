<?php

if( !chdir( dirname( __FILE__) ) )
	exit;

// We get hash only, so run this script in background and exit
if( count( $argv ) < 3 )
{
        require_once( "../../config.php" );
        if( !$pathToPHP || $pathToPHP == '' ) $pathToPHP = 'php';
        exec( $pathToPHP.' "'.basename( __FILE__ ).'" "'.$argv[1].'" --daemon > /dev/null 2>/dev/null &' );
        exit;
}

require_once( "../../util.php" );
require_once( "../../xmlrpc.php" );
require_once( "util_rt.php" );
require_once( "autotools.php" );
require_once( "conf.php" );

$AutoMove_Sem = rtSemGet( fileinode( __FILE__ ) );
rtSemLock( $AutoMove_Sem );

function Debug( $str )
{
	global $autodebug_enabled;
	if( $autodebug_enabled ) rtDbg( "AutoMove", $str );
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
	Debug( "enabled          : ".$at->enable_label );
	if( $at->enable_move )
	{
		$path_to_finished = trim( $at->path_to_finished );
		Debug( "path_to_finished : ".$path_to_finished );
		if( $path_to_finished != '' )
			$path_to_finished = rtAddTailSlash( $path_to_finished );
		else
			$is_ok = false;
	}
	else $is_ok = false;
}

// Ask info from rTorrent (torrent is assumed to be open)
if( $is_ok )
{
	$req = new rXMLRPCRequest( array(
		new rXMLRPCCommand( "get_directory" ),
		new rXMLRPCCommand( "d.get_base_path", $hash ),
	) );
	if( $req->run() && !$req->fault )
	{
		$directory = trim( $req->strings[0] );
		$base_path = trim( $req->strings[1] );
		Debug( "get_directory    : ".$directory );
		Debug( "d.get_base_path  : ".$base_path );
		if( $directory != '' && $base_path != '' )
		{
			$directory = rtAddTailSlash( $directory );
			// Make $base_path a really BASE path for downloading data
			// (not including single file or subdir for multiple files).
			// Add trailing slash, if none.
			$base_path = rtRemoveTailSlash( $base_path );
			$base_path = rtRemoveLastToken( $base_path, '/' );	// filename or dirname
			$base_path = rtAddTailSlash( $base_path );
			Debug( "base_path        : ".$base_path );
		}
		else {
			Debug( "base paths are empty!" );
			$is_ok = false;
		}
	}
	else {
		Debug( "rXMLRPCRequest() fail (get_directory, d.get_base_path)" );
		$is_ok = false;
	}
}

// Calc the destination directory
if( $is_ok )
{
	// Calc relative dir (and check if $base_path is a subdir of $directory)
	$rel_path = rtGetRelativePath( $directory, $base_path );
	Debug( "relative path    : ".$rel_path );
	if( $rel_path != '' )
	{
		if( $rel_path == './' ) $rel_path = '';
		// $dest_path is a new BASE path
		$dest_path = rtAddTailSlash( $path_to_finished.$rel_path );
		Debug( "dest_path        : ".$dest_path );
	}
	else {
		Debug( "Source files are not in subdir of \"directory\"" );
		$is_ok = false;
	}
}

// Change base_path and move files
if( $is_ok )
{
	if( !rtSetDataDir( $hash, $dest_path, true, $autodebug_enabled ) )
	{
		Debug( "rtSetDataDir() fail" );
	}
}

Debug( "--- end ---" );

rtSemUnlock( $AutoMove_Sem );

?>
