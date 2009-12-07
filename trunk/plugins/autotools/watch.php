<?php

if( !chdir( dirname( __FILE__ ) ) )
	exit;

//$path = dirname( realpath( $argv[0] ) );
//if( !chdir( $path ) )
//	exit;

require_once( "../../util.php" );
require_once( "../../xmlrpc.php" );
require_once( "util_rt.php" );
require_once( "autotools.php" );
require_once( "conf.php" );


function Debug( $str )
{
	global $autodebug_enabled;
	if( $autodebug_enabled ) rtDbg( "AutoWatch: ".$str );
}

Debug( "" );
Debug( "--- begin ---" );

$is_ok = true;

// Read configuration
if( $is_ok )
{
	$at = rAutoTools::load();
	Debug( "enabled          : ".$at->enable_watch );
	if( $at->enable_watch )
	{
		$path_to_watch = rtAddTailSlash( trim( $at->path_to_watch ) );
		Debug( "path_to_watch    : ".$path_to_watch );
		if( $path_to_watch == '' || $path_to_watch == '/' )
			$is_ok = false;
	}
	else $is_ok = false;
}

// Ask info from rTorrent
if( $is_ok )
{
	$req = rtExec( "get_directory", null, $autodebug_enabled );
	if( $req )
	{
		$directory = rtAddTailSlash( trim( $req->strings[0] ) );
		Debug( "get_directory    : ".$directory );
		if( $directory == '' || $directory == '/' )
			$is_ok = false;
	}
	else $is_ok = false;
}

// Scan for *.torrent files at $path_to_watch
if( $is_ok )
{
	$files = rtScanFiles( $path_to_watch, "*.torrent", true ); // ignore case
	foreach( $files as $file )
	{
		$torrent_file = realpath( $path_to_watch.$file );
		$dest_path = rtAddTailSlash( dirname( $directory.$file ) );
		Debug( "torrent file     : ".$torrent_file );
		Debug( "save data to     : ".$dest_path );
		if( rtMkDir( $dest_path, 0777 ) )
		{
			// sendFile2rTorrent($fname, $isURL, $isStart, $isAddPath, $directory, $label, $addition = '')
			$hash = sendFile2rTorrent(
				$torrent_file,			// path to .torrent file
				false,				// not URL
				false,				// don't start it
				true,				// add torrent's name to directory
				$dest_path,			// directory for torrent's data
				null				// label is emply
			);
			if( $hash === false )
			{
				Debug( "sendFile2rTorrent() fail" );
				rename( $torrent_file, $torrent_file."fail" );
				$is_ok = false;
			}
			else {
				Debug( "torrent added    : ".$hash );
			}
		}
		else {
			Debug( "can't create ".$dest_path );
			rename( $torrent_file, $torrent_file."fail" );
			$is_ok = false;
		}
	}
}

Debug( "--- end ---" );

?>
