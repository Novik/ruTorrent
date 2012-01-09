<?php

require_once( "../../php/xmlrpc.php" );
require_once( "../../php/Torrent.php" );

//------------------------------------------------------------------------------
// Debug stub
//------------------------------------------------------------------------------
function rtDbg( $prefix, $str )
{
	if( !$str )
		toLog( "" );
	elseif( $prefix && strlen( $prefix ) > 0 )
		toLog( $prefix.": ".$str );
	else
		toLog( $str );
}


//------------------------------------------------------------------------------
// Check if script was launched in background (with --daemon switch)
//------------------------------------------------------------------------------
function rtIsDaemon( $args )
{
	foreach( $args as $arg )
		if( $arg == '--daemon' )
			return true;
	return false;
}

//------------------------------------------------------------------------------
// Making current process a daemon (run in background)
//------------------------------------------------------------------------------
function rtDaemon( $php, $script, $args )
{
	if( !$php || $php == '' ) $php = 'php';
	$params = escapeshellarg( $script ).' --daemon';
	foreach( $args as $arg )
		$params .= ' '.escapeshellarg( $arg );
	exec( $php.' '.$params.' > /dev/null 2>/dev/null &', $out, $ret );
	exit( (int)$ret );
}


//------------------------------------------------------------------------------
// Operations with semaphores
//------------------------------------------------------------------------------
function rtSemGet( $id )
{
	//$available = in_array( "sysvsem", get_loaded_extensions() );
	$available = function_exists( "sem_get" );
	return $available ? sem_get( $id, 1 ) : false;
}

//------------------------------------------------------------------------------
function rtSemLock( $sem_key )
{
	if( $sem_key ) sem_acquire( $sem_key );
}

//------------------------------------------------------------------------------
function rtSemUnlock( $sem_key )
{
	if( $sem_key ) sem_release( $sem_key );
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
// Check if path is a file (without 2 Gb limit)
//------------------------------------------------------------------------------
function rtIsFile( $path )
{
	// use Novik's implementation
	return LFS::is_file( $path );

	//if( is_file( $path ) )
	//	return true;
	//$out = array();
	//$ret = "1";
	//exec( 'test -f '.escapeshellarg( $path ), $out, $ret );
	//return (int)$ret == 0;
}

//------------------------------------------------------------------------------
// Check if $dir exists and try to create it if not
//------------------------------------------------------------------------------
function rtMkDir( $dir, $mode = 0777 )
{
	if( !is_dir( $dir ) )
	{
		// recursive mkdir() only after PHP_5.0
		makeDirectory( $dir, $mode );
		//system( 'mkdir -p "'.$dst_dir.'"' );
		if( !is_dir( $dir ) )
			return false;
	}
	return true;
}

//------------------------------------------------------------------------------
// Move $src file to $dst
//------------------------------------------------------------------------------
function rtMoveFile( $src, $dst, $dbg = false )
{
	$atime = fileatime( $src );
	$mtime = filemtime( $src );
	if( !rename( $src, $dst ) )
	{
		if( $dbg ) rtDbg( __FUNCTION__, "from ".$src );
		if( $dbg ) rtDbg( __FUNCTION__, "to   ".$dst );
		if( $dbg ) rtDbg( __FUNCTION__, "move fail, try to copy" );
		if( !copy( $src, $dst ) )
		{
			if( $dbg ) rtDbg( __FUNCTION__, "copy fail" );
			return false;
		}
		if( !unlink( $src ) )
			if( $dbg ) rtDbg( __FUNCTION__, "delete fail (".$src.")" );
	}
	// there are problems here, if run-user is not file owner
	touch( $dst, $atime, $mtime );
	return true;
}

//------------------------------------------------------------------------------
// Make operation an array of files from $src directory to $dst directory
// ( files in array are relative to $src directory )
//------------------------------------------------------------------------------
function rtOpFiles( $files, $src, $dst, $op, $dbg = false )
{
	// Check if source and destination directories are valid
	if( !is_array( $files ) || $src == '' || $dst == '' )
	{
		if( $dbg ) rtDbg( __FUNCTION__, "invalid params" );
		return false;
	}

	// Check if source directory exists
	if( !is_dir( $src ) )
	{
		if( $dbg ) rtDbg( __FUNCTION__, "src is not a directory" );
		if( $dbg ) rtDbg( __FUNCTION__, "( ".$src." )" );
		return false;
	}
	else $src = rtAddTailSlash( $src );

	// Check if destination directory exists or can be created
	if( !rtMkDir( dirname( $dst ), 0777 ) )
	{
		if( $dbg ) rtDbg( __FUNCTION__, "can't create ".dirname( $dst ) );
		return false;
	}
	else $dst = rtAddTailSlash( $dst );

	// Check if source and destination directories are the same
	if( realpath( $src ) == realpath( $dst ) )
	{
		if( $dbg ) rtDbg( __FUNCTION__, "source is equal to destination" );
		if( $dbg ) rtDbg( __FUNCTION__, "( ".realpath( $src )." )" );
		return false;
	}

	foreach( $files as $file )
	{
		$source = $src.$file;
		$dest = $dst.$file;

		if( !rtMkDir( dirname( $dest ), 0777 ) )
		{
			if( $dbg ) rtDbg( __FUNCTION__, "can't create ".dirname( $dest ) );
			return false;
		}
		if( rtIsFile( $dest ) )
			unlink( $dest );
		switch( $op )
		{
			case "HardLink":
			{
				if( link( $source, $dest ) )
					break;
			}
			case "Copy":
			{
				if( !copy( $source, $dest ) )
					return false;
				break;
			}
			case "SoftLink":
			{
				if( !symlink( $source, $dest ) )
					return false;
				break;
			}
			default:
			{
				if( !rtMoveFile( $source, $dest, $dbg ) )
					return false;
				break;
			}
		}
	}
	if( $dbg ) rtDbg( __FUNCTION__, "finished" );
	return true;
}

//------------------------------------------------------------------------------
// Recursively scan files at $path directory
//------------------------------------------------------------------------------
function rtScanFiles( $path, $mask, $subdir = '' )
{
	$path = rtAddTailSlash( $path );
	if( $subdir != '' )
		$subdir = rtAddTailSlash( $subdir );
	$ret = array();
	if( is_dir( $path.$subdir ) )
	{
		$handle = opendir( $path.$subdir );
		while( false !== ( $item = readdir( $handle ) ) )
		{
			if( $item == '.' || $item == '..' )
				continue;
			$path_to_item = $path.$subdir.$item;
			if( is_dir( $path_to_item ) )
			{
				$ret = array_merge( $ret,
					rtScanFiles( $path, $mask, $subdir.$item ) );
			}
			elseif( rtIsFile( $path_to_item ) &&
				preg_match( $mask, $item ) )
			{
				$ret[] = $subdir.$item;
			}
		}
		closedir( $handle );
	}
	return ( $ret );
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
		if( $dbg ) rtDbg( __FUNCTION__, $cmds );
	}
	else {
		$s = '';
		foreach( $cmds as $cmd )
		{
			$s.= $cmd.", ";
			$req->addCommand( new rXMLRPCCommand( $cmd, $hash ) );
		}
		if( $dbg ) rtDbg( __FUNCTION__, substr( $s, 0, -2 ) );
	}
	if( !$req->run() )
	{
		if( $dbg ) rtDbg( __FUNCTION__, "rXMLRPCRequest() run fail" );
		return null;
	}
	elseif( $req->fault )
	{
		if( $dbg ) rtDbg( __FUNCTION__, "rXMLRPCRequest() fault" );
		return null;
	}
	else return $req;
}

//------------------------------------------------------------------------------
// Make string param for XMLRPC call
//------------------------------------------------------------------------------
function rtMakeStrParam( $param )
{
	return "<param><value><string>".$param."</string></value></param>";
}

//------------------------------------------------------------------------------
// Add ".torrent" file to rTorrent
//------------------------------------------------------------------------------
function rtAddTorrent( $fname, $isStart, $directory, $label, $dbg = false )
{
	if( $isStart )
		$method = 'load_start_verbose';
	else
		$method = 'load_verbose';

	if( $dbg ) rtDbg( __FUNCTION__, "1".$fname );
	$torrent = new Torrent($fname);
	if( $dbg ) rtDbg( __FUNCTION__, "2" );
	if( $torrent->errors() )
	{
		if( $dbg ) rtDbg( __FUNCTION__, "fail to create Torrent() object" );
		return false;
	}

	if( $directory && strlen( $directory ) > 0 )
	{
		$directory = rtMakeStrParam( "d.set_directory=\"".$directory."\"" );
	}
	else $directory = "";

	$comment = $torrent->comment();
	if( $comment && strlen( $comment ) > 0 )
	{
		if( isInvalidUTF8( $comment ) )
			$comment = win2utf($comment);
		if( strlen( $comment ) > 0 )
		{
			$comment = rtMakeStrParam( "d.set_custom2=VRS24mrker".rawurlencode( $comment ) );
			if(strlen($comment)>4096)
				$comment = '';
		}
	}
	else $comment = "";

	if( $label && strlen( $label ) > 0 )
	{
		$label = rtMakeStrParam( "d.set_custom1=\"".rawurlencode( $label )."\"" );
	}
	else $label = "";

	$addition = "";
	global $saveUploadedTorrents;
	$delete_tied = ($saveUploadedTorrents ? "" : rtMakeStrParam( "d.delete_tied=" ));

	$content =
		'<?xml version="1.0" encoding="UTF-8"?>'.
		'<methodCall>'.
		'<methodName>'.$method.'</methodName>'.
		'<params>'.
			'<param><value><string>'.$fname.'</string></value></param>'.
			$directory.
			$comment.
			$label.
			$addition.
			$delete_tied.
		'</params></methodCall>';

	//if( $dbg ) rtDbg( __FUNCTION__, $content );
	$res = rXMLRPCRequest::send( $content );

	if( $dbg && !empty($res) ) rtDbg( __FUNCTION__, $res );

	if( !$res || $res = '' )
		return false;
	else
		return $torrent->hash_info();
}


//------------------------------------------------------------------------------
// Move torrent data of $hash torrent to new location at $dest_path
//------------------------------------------------------------------------------
function rtSetDataDir( $hash, $dest_path, $add_path, $move_files, $dbg = false )
{
	if( $dbg ) rtDbg( __FUNCTION__, "hash        : ".$hash );
	if( $dbg ) rtDbg( __FUNCTION__, "dest_path   : ".$dest_path );
	if( $dbg ) rtDbg( __FUNCTION__, "add path    : ".($add_path ? "1" : "0") );
	if( $dbg ) rtDbg( __FUNCTION__, "move files  : ".($move_files ? "1" : "0") );

	$is_ok         = true;
	$is_open       = false;
	$is_active     = false;
	$is_multy_file = false;
	$base_name     = '';
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
			$is_open   = ( $req->val[0] != 0 );
			$is_active = ( $req->val[1] != 0 );
			if( $dbg ) rtDbg( __FUNCTION__, "is_open=".$req->val[0].", is_active=".$req->val[1] );
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
			array( "d.get_name", "d.get_base_path", "d.get_base_filename", "d.is_multi_file" ),
			$hash, $dbg );
		if( $req )
		{
			$is_multy_file = ( $req->val[3] != 0 );
			$base_name     = trim( $req->val[0] );
			$base_path     = trim( $req->val[1] );
			$base_file     = trim( $req->val[2] );
			if( $dbg ) rtDbg( __FUNCTION__, "d.get_name          : ".$base_name );
			if( $dbg ) rtDbg( __FUNCTION__, "d.get_base_path     : ".$base_path );
			if( $dbg ) rtDbg( __FUNCTION__, "d.get_base_filename : ".$base_file );
			if( $dbg ) rtDbg( __FUNCTION__, "d.is_multy_file     : ".$req->val[3] );
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
			if( $dbg ) rtDbg( __FUNCTION__, "base paths are empty" );
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
			$torrent_files = $req->val;
			if( $dbg ) rtDbg( __FUNCTION__, "files in torrent    : ".count( $torrent_files ) );
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
		$full_base_path = $base_path;
		$full_dest_path = $dest_path;
		// Don't use "count( $torrent_files ) > 1" check (there can be one file in a subdir)
		if( $is_multy_file )
		{
			// torrent is a directory
			$full_base_path .= rtAddTailSlash( $base_file );	
			$full_dest_path .= $add_path ? rtAddTailSlash( $base_name ) : "";
		}
		else {
			// torrent is a single file
		}

		if( $dbg ) rtDbg( __FUNCTION__, "from ".$full_base_path );
		if( $dbg ) rtDbg( __FUNCTION__, "to   ".$full_dest_path );
		
		if( $full_base_path != $full_dest_path && is_dir( $full_base_path ) )
		{
			if( rtOpFiles( $torrent_files, $full_base_path, $full_dest_path, "Move", $dbg ) )
			{
				// Recursively remove source dirs without files
				if( $dbg ) rtDbg( __FUNCTION__, "clean ".$full_base_path );
				if( $is_multy_file )
				{
					rtRemoveDirectory( $full_base_path, false );
					if( $dbg && is_dir( $full_base_path ) )
						rtDbg( __FUNCTION__, "some files were not deleted" );
				}
			}
			else $is_ok = false;
		}
	}

	// Setup new directory for torrent (we need to stop it first)
	if( $is_ok )
	{
		$is_ok = $add_path ? 
			rtExec( "d.set_directory",      array( $hash, $dest_path ), $dbg ) :
			rtExec( "d.set_directory_base", array( $hash, $dest_path ), $dbg );
	}

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

	if( $dbg ) rtDbg( __FUNCTION__, "finished" );
	return $is_ok;
}


?>