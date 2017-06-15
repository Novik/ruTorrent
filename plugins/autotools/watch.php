<?php

if( !chdir( dirname( __FILE__ ) ) )
	exit();

if( count( $argv ) > 1 )
	$_SERVER['REMOTE_USER'] = $argv[1];

require_once( "../../php/rtorrent.php" );
require_once( "./util_rt.php" );
require_once( "./autotools.php" );
eval( getPluginConf( 'autotools' ) );

function Debug( $str )
{
	global $autodebug_enabled;
	if( $autodebug_enabled ) rtDbg( "AutoWatch", $str );
}

Debug( "" );
Debug( "--- begin ---" );

$is_ok = true;

// Read configuration
if( $is_ok )
{
	$at = rAutoTools::load();
	Debug( "enabled          : ".$at->enable_watch );
	Debug( "autostart        : ".$at->watch_start );
	if( $at->enable_watch )
	{
		$auto_start = $at->watch_start;
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
	$directory = rtAddTailSlash( rTorrentSettings::get()->directory );
	Debug( "get_directory    : ".$directory );
	if( $directory == '' || $directory == '/' )
		$is_ok = false;
}

// Scan for *.torrent files at $path_to_watch
if( $is_ok )
{
	$files = rtScanFiles( $path_to_watch, "/.*\.torrent$/i" ); // ignore case
	foreach( $files as $file )
	{
		$torrent_file = $path_to_watch.$file; // don't use realpath() here !!!
		$dest_path = rtAddTailSlash( dirname( $directory.$file ) );
		Debug( "torrent file     : ".$torrent_file );
		Debug( "save data to     : ".$dest_path );

		$is_ok = true;
		// rTorrent store info about all added torrents,
		// so we must change $torrent_file's time to trick rTorrent.
		// ( If we want to add same torrent again )
		if( $is_ok && ( !is_writeable( $torrent_file ) || !touch( $torrent_file ) ) )
		{
			Debug( "no access to ".$torrent_file );
			$is_ok = false;
		}

		if( $is_ok && !rtMkDir( $dest_path, 0777 ) )
		{
			Debug( "can't create ".$dest_path );
			$is_ok = false;
		}


		if( $is_ok )
		{
			$hash = rTorrent::sendTorrent(
				$torrent_file,			// path to .torrent file
				$auto_start,			// start it or not
				true,				// add_path
				$dest_path,			// directory for torrent's data
				null,				// label is emply
				false,				// don't saveUploadedTorrents
				false				// don't fast_resume
			);
			if( $hash === false )
			{
				Debug( "addition of torrent fail" );
				$is_ok = false;
			}
			else {
				Debug( "torrent added    : ".$hash );
			}
		}

		if( !$is_ok )
			rename( $torrent_file, $torrent_file.".fail" );
	}
}


Debug( "--- end ---" );
