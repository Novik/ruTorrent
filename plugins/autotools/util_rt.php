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
		mkdir( $dir, $mode, true );
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
	$ss = LFS::stat($src);
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
	if($ss!==false)
		touch( $dst, $ss['mtime'], $ss['atime'] );
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