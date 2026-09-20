<?php

require_once( "../../php/xmlrpc.php" );
require_once( "../../php/Torrent.php" );
require_once( "../../php/rtorrent.php" );
require_once( './util_rt.php' );

//------------------------------------------------------------------------------
// Where a download's data sits now, and where a move would put it
//------------------------------------------------------------------------------
function rtDataDirPaths( $base_path, $base_file, $base_name, $dest_path,
	$add_path, $is_multy_file )
{
	// $base_path names the data itself: a file, or the directory a multi file
	// download's files sit in. What a move carries is what that stands in.
	$base_path = rtRemoveTailSlash( $base_path );
	$base_path = rtRemoveLastToken( $base_path, '/' );	// filename or dirname
	$full_base_path = rtAddTailSlash( $base_path );
	$full_dest_path = rtAddTailSlash( $dest_path );
	// Don't use "count( $torrent_files ) > 1" check (there can be one file in a subdir)
	if( $is_multy_file )
	{
		$full_base_path .= rtAddTailSlash( $base_file );
		$full_dest_path .= $add_path ? rtAddTailSlash( $base_name ) : "";
	}
	return array( $full_base_path, $full_dest_path );
}

//------------------------------------------------------------------------------
// The name under $dest_path that something already stands at, for the $hash
// download, or '' when the destination is free
//------------------------------------------------------------------------------
function rtDataDirCollision( $hash, $dest_path, $add_path, $dbg = false )
{
	if( $dest_path == '' )
		return '';

	// Read only: nothing about the download is changed to answer this. A
	// closed one is not opened to find out either -- rTorrent reports no base
	// path for it until it is -- so '' comes back and the collision is left to
	// the move, which is where it was found before this was asked at all.
	$req = rtExec( array(
			"d.is_open",
			"d.get_name",
			"d.get_base_path",
			"d.get_base_filename",
			"d.is_multi_file" ),
		$hash, $dbg );
	if( !$req || $req->val[0] == 0 )
		return '';
	$base_name = trim( $req->val[1] );
	$base_path = trim( $req->val[2] );
	$base_file = trim( $req->val[3] );
	if( $base_path == '' || $base_file == '' )
		return '';

	list( $full_base_path, $full_dest_path ) = rtDataDirPaths(
		$base_path, $base_file, $base_name, $dest_path,
		$add_path, ( $req->val[4] != 0 ) );

	// The same two conditions the move applies before it carries anything. A
	// destination that is where the data already is moves nothing, so every
	// name being taken there is the download's own doing and not a collision.
	if( $full_base_path == $full_dest_path || !is_dir( $full_base_path ) )
		return '';

	$req = rtExec( "f.multicall", array( $hash, "", getCmd( "f.get_path=" ) ), $dbg );
	if( !$req )
		return '';

	return rtTakenDestination( $req->val, $full_dest_path );
}

//------------------------------------------------------------------------------
// Move torrent data of $hash torrent to new location at $dest_path
//------------------------------------------------------------------------------
function rtSetDataDir( $hash, $dest_path, $add_path, $move_files, $fast_resume, $dbg = false )
{
	if( $dbg ) rtDbg( __FUNCTION__, "hash        : ".$hash );
	if( $dbg ) rtDbg( __FUNCTION__, "dest_path   : ".$dest_path );
	if( $dbg ) rtDbg( __FUNCTION__, "add path    : ".($add_path ? "1" : "0") );
	if( $dbg ) rtDbg( __FUNCTION__, "move files  : ".($move_files ? "1" : "0") );
	if( $dbg ) rtDbg( __FUNCTION__, "fast resume : ".($fast_resume ? "1" : "0") );

	$is_open       = false;
	$is_active     = false;
	$is_multy_file = false;
	$base_name     = '';
	$base_path     = '';
	$base_file     = '';

	$is_ok = true;
	if( $dest_path == '' )
	{
		$is_ok = false;
	}
	else {
		$dest_path = rtAddTailSlash( $dest_path );
	}

	// Check if torrent is open or active
	if( $is_ok )
	{
		$req = rtExec( array( "d.is_open", "d.is_active" ), $hash, $dbg );
		if( !$req )
			$is_ok = false;
		else {
			$is_open   = ( $req->val[0] != 0 );
			$is_active = ( $req->val[1] != 0 );
			if( $dbg ) rtDbg( __FUNCTION__, "is_open=".$req->val[0].", is_active=".$req->val[1] );
		}
	}

	// Open closed torrent to get d.get_base_path, d.get_base_filename
	if( $is_ok && $move_files )
	{
		if( !$is_open && !rtExec( "d.open", $hash, $dbg ) )
		{
			$is_ok = false;
		}
	}

	// Ask info from rTorrent
	if( $is_ok && $move_files )
	{
		$req = rtExec(
			array( 	"d.get_name",
				"d.get_base_path",
				"d.get_base_filename",
				"d.is_multi_file",
				"d.get_complete" ),
			$hash, $dbg );
		if( !$req )
			$is_ok = false;
		else {
			$base_name     = trim( $req->val[0] );
			$base_path     = trim( $req->val[1] );
			$base_file     = trim( $req->val[2] );
			$is_multy_file = ( $req->val[3] != 0 );
			if( $req->val[4] == 0 ) // if torrent is not completed -> "fast start" is impossible
				$fast_resume = false;
			if( $dbg ) rtDbg( __FUNCTION__, "d.get_name          : ".$base_name );
			if( $dbg ) rtDbg( __FUNCTION__, "d.get_base_path     : ".$base_path );
			if( $dbg ) rtDbg( __FUNCTION__, "d.get_base_filename : ".$base_file );
			if( $dbg ) rtDbg( __FUNCTION__, "d.is_multy_file     : ".$req->val[3] );
			if( $dbg ) rtDbg( __FUNCTION__, "d.get_complete      : ".$req->val[4] );
		}
	}

	// Check if paths are valid
	if( $is_ok && $move_files )
	{
		if( $base_path == '' || $base_file == '' )
		{
			if( $dbg ) rtDbg( __FUNCTION__, "base paths are empty" );
			$is_ok = false;
		}
	}

	// Get list of torrent data files
	$torrent_files = array();
	if( $is_ok && $move_files )
	{
		$req = rtExec( "f.multicall", array( $hash, "", getCmd("f.get_path=") ), $dbg );
		if( !$req )
			$is_ok = false;
		else {
			$torrent_files = $req->val;
			if( $dbg ) rtDbg( __FUNCTION__, "files in torrent    : ".count( $torrent_files ) );
		}
	}

	// 1. Stop torrent if active (if not, then rTorrent can crash)
	// 2. Close torrent anyway
	if( $is_ok )
	{
		$cmds = array();
		if( $is_active ) $cmds[] = "d.stop";
		if( $is_open || $move_files ) $cmds[] = "d.close";
		if( count( $cmds ) > 0 && !rtExec( $cmds, $hash, $dbg ) )
			$is_ok = false;
	}

	// Move torrent data files to new location
	if( $is_ok && $move_files )
	{
		list( $full_base_path, $full_dest_path ) = rtDataDirPaths(
			$base_path, $base_file, $base_name, $dest_path,
			$add_path, $is_multy_file );

		if( $dbg ) rtDbg( __FUNCTION__, "from ".$full_base_path );
		if( $dbg ) rtDbg( __FUNCTION__, "to   ".$full_dest_path );

		if( $full_base_path != $full_dest_path && is_dir( $full_base_path ) )
		{
			if( !rtOpFiles( $torrent_files, $full_base_path, $full_dest_path, "Move", $dbg ) )
			{
				// The download was stopped and closed above so that rtorrent
				// would let go of the files. The move did not happen, so the
				// data is where it has always been and the download can run on
				// it -- but everything after this point is behind $is_ok, the
				// restart included. Give it back the way it was found, rather
				// than leave it stopped with nothing saying why.
				$restore = array();
				if( $is_open || $is_active ) $restore[] = "d.open";
				if( $is_active ) $restore[] = "d.start";
				if( count( $restore ) > 0 )
					rtExec( $restore, $hash, $dbg );
				$is_ok = false;
			}
			else {
				// Recursively remove source dirs without files
				if( $dbg ) rtDbg( __FUNCTION__, "clean ".$full_base_path );
				if( $is_multy_file )
				{
					rtRemoveDirectory( $full_base_path, false );
					if( $dbg && is_dir( $full_base_path ) )
						rtDbg( __FUNCTION__, "some files were not deleted" );
				}
			}
		}
	}

	if( $is_ok )
	{
		// fast resume is requested
		if( $fast_resume )
		{
			if( $dbg ) rtDbg( __FUNCTION__, "trying fast resume" );
			// collect variables
			$session      = rTorrentSettings::get()->session;
			$tied_to_file = null;
			$label        = null;
			$addition     = null;
			$req = rtExec( array(
					"get_session",
					"d.get_tied_to_file",
					"d.get_custom1",
					"d.get_connection_seed",
					"d.get_throttle_name",
					),
					$hash, $dbg );
			if( !$req )
			{
				$fast_resume = false;
			}
			else {
				$session      = $req->val[0];
				$tied_to_file = $req->val[1];
				$label        = rawurldecode( $req->val[2] );
				$addition     = array();
				// Both values were read back through rXMLRPCRequest, which
				// escapes every string it returns, and both are quoted on the
				// way out -- so they are put back into their own bytes first
				// or the quoting escapes that escaping a second time.
				if( !empty( $req->val[3] ) )
					$addition[] = rTorrent::additionCommand( "d.set_connection_seed",
						rXMLRPCRequest::unescapeValue( $req->val[3] ) );
				if( !empty( $req->val[4] ) )
					$addition[] = rTorrent::additionCommand( "d.set_throttle_name",
						rXMLRPCRequest::unescapeValue( $req->val[4] ) );
				// build path to .torrent file
				$fname = rtAddTailSlash( $session ).$hash.".torrent";
				if( empty( $session ) || !is_readable( $fname ) )
				{
					if( !strlen( $tied_to_file ) || !is_readable( $tied_to_file ) )
					{
						if( $dbg ) rtDbg( __FUNCTION__, "empty session or inaccessible .torrent file" );
						$fname = null;
						$fast_resume = false;
					}
					else {
						$fname = $tied_to_file;
					}
				}
			}

			// create torrent, remove old and add new one
			if( $fast_resume )
			{
				$torrent = new Torrent( $fname );
				if( $torrent->errors() )
				{
					if( $dbg ) rtDbg( __FUNCTION__, "fail to create Torrent object" );
					$fast_resume = false;
				}
				else
				{
					$is_ok = $add_path ?
						rtExec( "d.set_directory",      array( $hash, $dest_path ), $dbg ) :
						rtExec( "d.set_directory_base", array( $hash, $dest_path ), $dbg );	// for erasedata plugin

                                        if( $is_ok )
					{
						if( !rtExec( "d.erase", $hash, $dbg ) )
						{
							if( $dbg ) rtDbg( __FUNCTION__, "fail to erase old torrent" );
							$fast_resume = false;
						}
						else
						{
							if( !rTorrent::sendTorrent(
								$torrent,	// $fname or $torrent
								true, 		// $isStart
								$add_path, 	// $isAddPath
								$dest_path, 	// $directory
								$label,		// $label
								true, 		// $saveTorrent
								true, 		// $isFast
								false,		// $isNew
								$addition	// $addition
								) )
							{
								if( $dbg ) rtDbg( __FUNCTION__, "fail to add new torrent" );
								$fast_resume = false;
								$is_ok = false;
							}
						}
					}
				}
			}
			if( $dbg )
				rtDbg( __FUNCTION__, "fast resume ".($fast_resume ? "done" : "fail") );
		}

		// fast resume is fail or not requested at all
		if( $is_ok && !$fast_resume )
		{
			// Setup new directory for torrent (we need to stop it first)
			$is_ok = $add_path ?
				rtExec( "d.set_directory",      array( $hash, $dest_path ), $dbg ) :
				rtExec( "d.set_directory_base", array( $hash, $dest_path ), $dbg );

			if( $is_ok )
			{
				// Start torrent if need
				if( $is_active )
					$is_ok = rtExec( array( "d.open", "d.start" ), $hash, $dbg );
				// Open torrent if need
				elseif( $is_open )
					$is_ok = rtExec( "d.open", $hash, $dbg );
				// Refresh torrent info after d.set_directory
				else
					$is_ok = rtExec( array( "d.open", "d.close" ), $hash, $dbg );
			}
		}
	}

	if( $dbg ) rtDbg( __FUNCTION__, "finished" );
	return $is_ok;
}
