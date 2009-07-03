<?php

$rootPath = "./";
if( !is_file( "util.php" ) ) $rootPath = "../../";
require_once( $rootPath."util.php" );
require_once( $rootPath."xmlrpc.php" );
require_once( "at_utils.php" );
require_once( "autotools.php" );
require_once( "conf.php" );

Debug( "" );
Debug( "--- move.php begin ---" );

if( count( $argv ) < 1 )
{
	Debug( "Called without arguments (hash wanted)" );
	exit;
}
$hash = $argv[1];

// Read configuration
$at = rAutoTools::load();
Debug( "AutoMove state  : ".$at->enable_move );
if( !$at->enable_move )
	exit;

$path_to_finished = trim( $at->path_to_finished );
Debug( 'path_to_finished is "'.$path_to_finished.'"' );
if( $path_to_finished == '' )
	exit;
$path_to_finished = AddTailSlash( $path_to_finished );


// Ask info from rTorrent
$req = new rXMLRPCRequest( array(
	new rXMLRPCCommand( "get_directory" ),
	new rXMLRPCCommand( "d.get_base_path",     $hash ),
	new rXMLRPCCommand( "d.get_base_filename", $hash ),
	new rXMLRPCCommand( "d.is_multi_file",     $hash ),
) );
if( $req->run() && !$req->fault )
{
	//$i = 0;
	//foreach( $req->strings as $str ) Debug( $i++.' '.$str );
	//$i = 0;
	//foreach( $req->i8s as $str ) Debug( $i++.' '.$str );

	$is_multy_file = ( $req->i8s[0] != 0 );
	$default_dir = trim( $req->strings[0] );
	$base_path   = trim( $req->strings[1] );
	$base_file   = trim( $req->strings[2] );
	Debug( "get_directory       : ".$default_dir );
	Debug( "d.get_base_path     : ".$base_path );
	Debug( "d.get_base_filename : ".$base_file );
	Debug( "d.is_multy_file     : ".$is_multy_file );
	if( $default_dir == '' || $base_path == '' || $base_file == '' )
		exit;
	$default_dir = AddTailSlash( $default_dir );

	// Make $base_path a really BASE path for downloading data
	// (not including single file or subdir for multiple files).
	// Add trailing slash, if none.
	$base_path = RemoveTailSlash( $base_path );
	$base_path = RemoveLastToken( $base_path, '/' );	// filename or dirname
	$base_path = AddTailSlash( $base_path );

	// Check if files are in $path_to_finished dir already
	if( GetRelativePath( $path_to_finished, $base_path ) != '' )
	{
		Debug( "torrent files are already in subdir of \"path_to_finished\"" );
		exit;
	}

	// Calc relative dir (and check if $base_path is not a subdir of $default_dir)
	$rel_path = GetRelativePath( $default_dir, $base_path );
	if( $rel_path == '' )
	{
		Debug( "torrent files are not in subdir of \"directory\"" );
		exit;
	}
	if( $rel_path == './' ) $rel_path = '';

	// Set $dest_path as a new BASE path
	$dest_path = AddTailSlash( $path_to_finished.$rel_path );
	Debug( "dest_path           : ".$dest_path );

	// Get list of files in torrent
	$req = new rXMLRPCRequest(
		new rXMLRPCCommand( "f.multicall", array( $hash, "", "f.get_path=" )
	) );
	if( $req->run() && !$req->fault )
		$torrent_files = $req->strings;
	Debug( "files in torrent    : ".count( $torrent_files ) );
	if( count( $torrent_files ) == 0 )
		exit;


	// Stop active torrent (if not, then rTorrent can crash)
	$req = new rXMLRPCRequest( array(
		new rXMLRPCCommand( "d.stop",  $hash ),
		//new rXMLRPCCommand( "d.close", $hash ),
	) );
	if( !$req->run() || $req->fault )
	{
		Debug( "rXMLRPCRequest() fail (d.stop && d.close)!" );
		exit;
	}

	// Move torrent files to new location
	// Don't use "count( $torrent_files ) > 1" check (can be one file in a subdir)
	if( $is_multy_file )
		$sub_dir = AddTailSlash( $base_file );	// $base_file - is a directory
	else
		$sub_dir = '';				// $base_file - is really a file
	Debug( "move started: ".$base_path.$sub_dir." -> ".$dest_path.$sub_dir );
	foreach( $torrent_files as $file )
	{
		$src = $base_path.$sub_dir.$file;
		$dst = $dest_path.$sub_dir.$file;
		//Debug( "move ".$src." to ".$dst );
		if( is_file( $src ) )
		{
			if( !is_dir( dirname( $dst ) ) )
			{
				// recursive mkdir() only after PHP_5.0
				mkdir( dirname( $dst ), 0777, true );
				//system( 'mkdir -p "'.dirname( $dst ).'"' );
			}
			if( is_file( $dst ) )
				unlink( $dst );
			$atime = fileatime( $src );
			$mtime = filemtime( $src );
			if( !rename( $src, $dst ) )
			{
				Debug( "move fail: ".$src." -> ".$dst );
				if( !copy( $src, $dst ) )
				{
					Debug( "copy fail: ".$src." -> ".$dst );
					exit;
				}
				else unlink( $src );
			}
			touch( $dst, $atime, $mtime );
		}
	}
	// Recursively remove dirs without files
	if( $sub_dir != '' )
		RemoveDirectory( $base_path.$sub_dir, false );

	// Setup new directory for torrent & start it (we need to stop it first)
	Debug( "execute d.set_directory=".$dest_path );
	$req = new rXMLRPCRequest( array(
		new rXMLRPCCommand( "d.set_directory", array( $hash, $dest_path ) ),
		//new rXMLRPCCommand( "d.open",  $hash ),
		new rXMLRPCCommand( "d.start", $hash ),
	) );
	if( !$req->run() || $req->fault )
		Debug( "rXMLRPCRequest() fail (d.set_directory && d.open && d.start)!" );
}
else {
	Debug( "rXMLRPCRequest() fail (get info)!" );
}

Debug( "--- move.php end ---" );

?>
