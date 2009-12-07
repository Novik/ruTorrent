<?php

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



$files = rtScanFiles( "/etc", "*.conf" );
foreach( $files as $file )
{
	Debug( $file );
}
exit;


$is_ok = true;
if( count( $argv ) >= 1 )
{
	$torrent_file = $argv[1];
	Debug( "torrent file     : ".$torrent_file );
	$torrent_file = realpath( $torrent_file );
	@chmod( $torrent_file, 0666 );
	Debug( "torrent realpath : ".$torrent_file );

//<------>if(!_64.match(".torrent")) ^
//<------><------>alert(WUILang.Not_torrent_file);^

}
else {
	Debug( "torrent file not set (path wanted)" );
	$is_ok = false;
}

// Read configuration
//if( $is_ok )
//{
//	$at = rAutoTools::load();
//	Debug( "enabled          : ".$at->enable_label );
//	if( $at->enable_move )
//	{
//		$path_to_finished = trim( $at->path_to_finished );
//		Debug( "path_to_finished : ".$path_to_finished );
//		if( $path_to_finished != '' )
//			$path_to_finished = rtAddTailSlash( $path_to_finished );
//		else
//			$is_ok = false;
//	}
//	else $is_ok = false;
//}

// Ask info from rTorrent
if( $is_ok )
{
	$req = rtExec( "get_directory", null, $autodebug_enabled );
	if( $req )
	{
		$directory = trim( $req->strings[0] );
		Debug( "get_directory    : ".$directory );
		if( $directory != '' )
		{
			$directory = rtAddTailSlash( $directory );
			$dest_path = $directory;
		}
		else $is_ok = false;
	}
	else $is_ok = false;
}


// Add torrent to rTorrent
if( $is_ok )
{
	//$dest_path = '';
	Debug( "save torrent to  : ".$dest_path );

	// sendFile2rTorrent($fname, $isURL, $isStart, $isAddPath, $directory, $label, $addition = '')
	$hash = sendFile2rTorrent(
		$torrent_file,			// path to .torrent file
		false,				// not URL
		true,				// start it
		true,				// add torrent's name to directory
		$dest_path,			// directory for torrent's data
		null				// label is emply
	);
	if( $hash === false )
	{
		Debug( "torrent was not added" );
		rename( $torrent_file, $torrent_file."fail" );
		$is_ok = false;
	}
	else {
		Debug( "added as         : ".$hash );
	}
}

// Calc the destination directory
//if( $is_ok )
//{
//	// Calc relative dir (and check if $base_path is a subdir of $directory)
//	$rel_path = rtGetRelativePath( $directory, $base_path );
//	Debug( "relative path    : ".$rel_path );
//	if( $rel_path != '' )
//	{
//		if( $rel_path == './' ) $rel_path = '';
//		// $dest_path is a new BASE path
//		$dest_path = rtAddTailSlash( $path_to_finished.$rel_path );
//		Debug( "dest_path        : ".$dest_path );
//	}
//	else {
//		Debug( "Torrent files are not in subdir of \"directory\"" );
//		$is_ok = false;
//	}
//}

Debug( "--- end ---" );

?>
