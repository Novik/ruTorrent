<?php

require_once( "../../util.php" );
require_once( "../../xmlrpc.php" );

//------------------------------------------------------------------------------
// Debug stub
//------------------------------------------------------------------------------
function rtDbg( $str )
{
	toLog( $str );
}

//------------------------------------------------------------------------------
// Operations with slashes in paths
//------------------------------------------------------------------------------
function rtAddTailSlash( $str )
{
	$len = strlen( $str );
	if( $len > 0 && $str[$len-1] == '/' )
		return $str;
	return $str.'/';
}

//------------------------------------------------------------------------------
function rtRemoveTailSlash( $str )
{
	$len = strlen( $str );
	if( $len == 0 || $str[$len-1] != '/' )
		return $str;
	return substr( $str, 0, -1 );
}

//------------------------------------------------------------------------------
function rtRemoveHeadSlash( $str )
{
	$len = strlen( $str );
	if( $len == 0 || $str[0] != '/' )
		return $str;
	return substr( $str, 1 );
}

//------------------------------------------------------------------------------
// Remove last token from $str string, using $sep as separator
//------------------------------------------------------------------------------
function rtRemoveLastToken( $str, $sep )
{
	$pos = strrpos( $str, $sep );
	if( $pos === false )
		return $str;
	return substr( $str, 0, $pos );
}

//------------------------------------------------------------------------------
// Return a part of $real_dir path, relative to $base_dir
//------------------------------------------------------------------------------
function rtGetRelativePath( $base_dir, $real_dir )
{
	$base_dir = rtAddTailSlash( $base_dir );
	$len = strlen( $base_dir );
	$str = substr( $real_dir, 0, $len );
	if( $str != $base_dir )
		return '';			// $real_dir is NOT SUBDIR of $base_dir
	$str = substr( $real_dir, $len );
	if( $str != '' )
		return $str;			// $read_dir is SUBDIR of $base_dir
	return './';				// $real_dir is EQUAL to $base_dir
}

//------------------------------------------------------------------------------
// Move an array of files from $src directory to $dst directory
// ( files in array are relative to $src directory )
//------------------------------------------------------------------------------
function rtMoveFiles( $files, $src, $dst, $dbg = false )
{
	// Check if source and destination directories are valid
	if( !is_array( $files ) || $src == '' || $dst == '' )
	{
		if( $dbg ) rtDbg( "rtMoveFiles: invalid params!" );
		return false;
	}

	// Check if source directory exists
	if( !is_dir( $src ) )
	{
		if( $dbg ) rtDbg( "rtMoveFiles: src is not a directory!" );
		if( $dbg ) rtDbg( "rtMoveFiles: ( ".$src." )" );
		return false;
	}

	// Check if destination directory exists or can be created
	$src = rtAddTailSlash( $src );
	if( !is_dir( dirname( $dst ) ) )
	{
		// recursive mkdir() only after PHP_5.0
		mkdir( dirname( $dst ), 0777, true );
		//system( 'mkdir -p "'.dirname( $dst_dir ).'"' );
		if( !is_dir( dirname( $dst ) ) )
		{
			if( $dbg ) rtDbg( "rtMoveFiles: can't create dir ".$dst."!" );
			return false;
		}
		$dst = rtAddTailSlash( $dst );
	}

	// Check if source and destination directories are the same
	if( realpath( $src ) == realpath( $dst ) )
	{
		if( $dbg ) rtDbg( "rtMoveFiles: source is equal to destination!" );
		if( $dbg ) rtDbg( "rtMoveFiles: ( ".realpath( $src )." )" );
		return false;
	}

	// Move files
	if( $dbg ) rtDbg( "rtMoveFiles: from ".$src );
	if( $dbg ) rtDbg( "rtMoveFiles: to   ".$dst );
	foreach( $files as $file )
	{
		$src_f = $src.$file;
		$dst_f = $dst.$file;
		//if( $dbg ) rtDbg( "rtMoveFiles: move ".$src." to ".$dst );
		if( is_file( $src_f ) )
		{
			if( !is_dir( dirname( $dst_f ) ) )
			{
				// recursive mkdir() only after PHP_5.0
				mkdir( dirname( $dst_f ), 0777, true );
				//system( 'mkdir -p "'.dirname( $dst ).'"' );
			}
			if( is_file( $dst_f ) )
				unlink( $dst_f );
			$atime = fileatime( $src_f );
			$mtime = filemtime( $src_f );
			if( !rename( $src_f, $dst_f ) )
			{
				if( $dbg ) rtDbg( "rtMoveFiles: move fail for ".$file );
				if( $dbg ) rtDbg( "rtMoveFiles: try to copy ".$file );
				if( !copy( $src_f, $dst_f ) )
				{
					if( $dbg ) rtDbg( "rtMoveFiles: copy fail for ".$file );
					return false;
				}
				if( !unlink( $src_f ) )
					if( $dbg ) rtDbg( "rtMoveFiles: delete fail for ".$file );
			}
			// there are problems here, if run-user is not file owner
			//touch( $dst_f, $atime, $mtime );
		}
	}
	if( $dbg ) rtDbg( "rtMoveFiles: finished" );
	return true;
}

//------------------------------------------------------------------------------
// Recursively remove $path directory (optionally with or without files)
//------------------------------------------------------------------------------
function rtRemoveDirectory( $path, $with_files = false )
{
	$path = rtRemoveTailSlash( $path );
	if( !file_exists( $path ) || !is_dir( $path ) )
		return false;
	$handle = opendir( $path );
	$empty = true;
	while( false !== ( $item = readdir( $handle ) ) )
	{
		if( $item == '.' || $item == '..' )
			continue;
		$path_to_item = $path.'/'.$item;
		if( is_dir( $path_to_item ) )
		{
			if( !rtRemoveDirectory( $path_to_item, $with_files ) )
				$empty = false;
		}
		else
		{
			if( !$with_files || !unlink( $path_to_item ) )
				$empty = false;
		}
	}
	closedir( $handle );
	return ( $empty && rmdir( $path ) );
}


//------------------------------------------------------------------------------
// Exec $cmds set of commands for the $hash torrent
//------------------------------------------------------------------------------
function rtExec( $cmds, $hash, $dbg )
{
	$req = new rXMLRPCRequest();
	if( !is_array( $cmds ) )
	{
		$req->addCommand( new rXMLRPCCommand( $cmds, $hash ) );
		if( $dbg ) rtDbg( "rtExec: execute ".$cmds );
	}
	else {
		$s = '';
		foreach( $cmds as $cmd )
		{
			$s.= $cmd.", ";
			$req->addCommand( new rXMLRPCCommand( $cmd, $hash ) );
		}
		if( $dbg ) rtDbg( "rtExec: execute ".substr( $s, 0, -2 ) );
	}
	if( !$req->run() || $req->fault )
	{
		if( $dbg ) rtDbg( "rtExec: rXMLRPCRequest() fail (".$cmds.")!" );
		return null;
	}
	return $req;
}


//------------------------------------------------------------------------------
// Move torrent data of $hash torrent to new location at $dest_path
//------------------------------------------------------------------------------
function rtSetDataDir( $hash, $dest_path, $move_files, $dbg = false )
{
	if( $dbg ) rtDbg( "rtSetDataDir: ".$dest_path.", ".$hash );

	$is_ok         = true;
	$is_open       = false;
	$is_active     = false;
	$is_multy_file = false;
	$base_path     = '';
	$base_file     = '';

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
		if( $req )
		{
			$is_open   = ( $req->i8s[0] != 0 );
			$is_active = ( $req->i8s[1] != 0 );
			if( $dbg ) rtDbg( "rtSetDataDir: is_open=".$req->i8s[0].", is_active=".$req->i8s[1] );
		}
		else $is_ok = false;
	}

	// Open closed torrent to get d.get_base_path, d.get_base_filename
	if( $is_ok && $move_files )
	{
		if( !$is_open )
			$is_ok = rtExec( "d.open", $hash, $dbg );
	}

	// Ask info from rTorrent
	if( $is_ok && $move_files )
	{
		$req = rtExec(
			array( "d.get_base_path", "d.get_base_filename", "d.is_multi_file" ),
			$hash, $dbg );
		if( $req )
		{
			//$i = 0;
			//foreach( $req->strings as $str ) Debug( $i++.' '.$str );
			//$i = 0;
			//foreach( $req->i8s as $str ) Debug( $i++.' '.$str );
			$is_multy_file = ( $req->i8s[0] != 0 );
			$base_path     = trim( $req->strings[0] );
			$base_file     = trim( $req->strings[1] );
			if( $dbg ) rtDbg( "rtSetDataDir: d.get_base_path     : ".$base_path );
			if( $dbg ) rtDbg( "rtSetDataDir: d.get_base_filename : ".$base_file );
			if( $dbg ) rtDbg( "rtSetDataDir: d.is_multy_file     : ".$req->i8s[0] );
		}
		else $is_ok = false;
	}

	// Check if paths are valid
	if( $is_ok && $move_files )
	{
		if( $base_path != '' && $base_file != '' )
		{
			// Make $base_path a really BASE path for downloading data
			// (not including single file or subdir for multiple files).
			// Add trailing slash, if none.
			$base_path = rtRemoveTailSlash( $base_path );
			$base_path = rtRemoveLastToken( $base_path, '/' );	// filename or dirname
			$base_path = rtAddTailSlash( $base_path );
		}
		else {
			if( $dbg ) rtDbg( "rtSetDataDir: base paths are empty!" );
			$is_ok = false;
		}
	}

	// Get list of torrent data files
	$torrent_files = array();
	if( $is_ok && $move_files )
	{
		$req = rtExec( "f.multicall", array( $hash, "", "f.get_path=" ), $dbg );
		if( $req )
		{
			$torrent_files = $req->strings;
			if( $dbg ) rtDbg( "rtSetDataDir: files in torrent    : ".count( $torrent_files ) );
		}
		else $is_ok = false;
	}

	// 1. Stop torrent if active (if not, then rTorrent can crash)
	// 2. Close torrent anyway
	if( $is_ok )
	{
		$cmd = array();
		if( $is_active ) $cmd[] = "d.stop";
		if( $is_open || $move_files ) $cmd[] = "d.close";
		if( count( $cmd ) > 0 )
			$is_ok = rtExec( $cmd, $hash, $dbg );
	}

	// Move torrent data files to new location
	if( $is_ok && $move_files )
	{
		// Don't use "count( $torrent_files ) > 1" check (can be one file in a subdir)
		if( $is_multy_file )
			$sub_dir = rtAddTailSlash( $base_file );	// $base_file - is a directory
		else
			$sub_dir = '';					// $base_file - is really a file

		if( $dbg ) rtDbg( "rtSetDataDir: from ".$base_path.$sub_dir );
		if( $dbg ) rtDbg( "rtSetDataDir: to   ".$dest_path.$sub_dir );
		if( $base_path.$sub_dir != $dest_path.$sub_dir && is_dir( $base_path.$sub_dir ) )
		{
			if( rtMoveFiles( $torrent_files, $base_path.$sub_dir, $dest_path.$sub_dir, $dbg ) )
			{
				// Recursively remove source dirs without files
				if( $dbg ) rtDbg( "rtSetDataDir: clean ".$base_path.$sub_dir );
				if( $sub_dir != '' )
				{
					rtRemoveDirectory( $base_path.$sub_dir, false );
					if( $dbg && is_dir( $base_path.$sub_dir ) )
						rtDbg( "rtSetDataDir: some files were not deleted!" );
				}
			}
			else $is_ok = false;
		}
	}

	// Setup new directory for torrent (we need to stop it first)
	if( $is_ok )
	{
		$is_ok = rtExec( "d.set_directory", array( $hash, $dest_path ), $dbg );
	}

	if( $is_ok )
	{
		$cmd = array();
		// Open torrent if need
		if( $is_open )   $cmd[] = "d.open";
		// Start torrent if need
		if( $is_active ) $cmd[] = "d.start";
		if( count( $cmd ) > 0 )
			$is_ok = rtExec( $cmd, $hash, $dbg );
	}

	if( $dbg ) rtDbg( "rtSetDataDir: finished" );
	return $is_ok;
}


?>
