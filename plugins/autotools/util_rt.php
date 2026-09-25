<?php

require_once( "../../php/xmlrpc.php" );
require_once( "../../php/Torrent.php" );

//------------------------------------------------------------------------------
// Debug stub
//------------------------------------------------------------------------------
function rtDbg( $prefix, $str )
{
	if( !$str )
		FileUtil::toLog( "" );
	elseif( $prefix && strlen( $prefix ) > 0 )
		FileUtil::toLog( $prefix.": ".$str );
	else
		FileUtil::toLog( $str );
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
// The destination root as something nothing can be smuggled out of: the
// caller's directory with every symlink in it already resolved. Names are
// built from this, so a symlink above the root -- a download directory that is
// a link into a storage pool, which is the usual layout -- is followed once
// here and is not walked through again for every file.
//------------------------------------------------------------------------------
function rtDestinationRoot( $dst )
{
	$dst = rtRemoveTailSlash( $dst );
	if( $dst == '' )
		return '';
	$real = realpath( $dst );
	if( $real !== false )
		return rtRemoveTailSlash( $real );
	// Not there yet, so there is nothing in it to resolve. The deepest part of
	// it that is there is resolved instead, and what is added to that is
	// elements that do not exist and so cannot be symlinks. rtOpFiles()
	// confirms that is still so once it has made them.
	$tail = array();
	$path = $dst;
	while( true )
	{
		$parent = dirname( $path );
		if( $parent === $path )
			return '';
		array_unshift( $tail, basename( $path ) );
		$real = realpath( $parent );
		if( $real !== false )
			return rtRemoveTailSlash( $real ).'/'.implode( '/', $tail );
		$path = $parent;
	}
}

//------------------------------------------------------------------------------
// The path $file names under $root, with every element of the way down to it
// checked, or '' when it cannot be reached without leaving $root.
//
// $root is canonical, so the only way out of it is through the elements of
// $file, and lstat() is what sees them: it reports a symlink as a symlink
// instead of as whatever it points at. Every call the operations end in --
// mkdir(), rename(), copy(), link(), symlink() -- follows a symlinked element
// and lands where it leads, so an element that is already there and is not a
// directory of its own ends the walk.
//
// With $make the missing elements are created, one level at a time so that
// each is checked as it is reached rather than handed to a recursive mkdir()
// that checks none of them, and each one made is appended to $made.
//------------------------------------------------------------------------------
function rtContainedPath( $root, $file, $make = false, &$made = null )
{
	$root = rtRemoveTailSlash( $root );
	if( $root == '' || !is_string( $file ) || $file == '' || $file[0] == '/' )
		return '';
	$parts = explode( '/', $file );
	$leaf = array_pop( $parts );
	if( $leaf == '' || $leaf == '.' || $leaf == '..' )
		return '';
	$path = $root;
	foreach( $parts as $part )
	{
		if( $part == '' || $part == '.' || $part == '..' )
			return '';
		$path .= '/'.$part;
		$st = @lstat( $path );
		if( $st === false )
		{
			// Something is there that cannot be inspected. What it is decides
			// where the walk goes, so not knowing ends it.
			if( is_link( $path ) || file_exists( $path ) )
				return '';
			if( !$make )
				continue;
			if( !@mkdir( $path, 0777 ) )
			{
				// Lost the race to create it. What won is only acceptable if
				// it is the directory this was going to make.
				$st = @lstat( $path );
				if( $st === false || ( $st['mode'] & 0170000 ) != 0040000 )
					return '';
				continue;
			}
			if( is_array( $made ) )
				$made[] = array( 'rmdir', $path );
			continue;
		}
		if( ( $st['mode'] & 0170000 ) != 0040000 )
			return '';
	}
	return $path.'/'.$leaf;
}

//------------------------------------------------------------------------------
// Whether anything at all stands at $path. A directory cannot be written over,
// and a symlink is a name that belongs to whatever it points at -- writing
// through it destroys that, and a dangling one is still somebody's.
//------------------------------------------------------------------------------
function rtNameInUse( $path )
{
	return rtIsFile( $path ) || is_dir( $path ) || is_link( $path ) ||
		file_exists( $path );
}

//------------------------------------------------------------------------------
// The first name under $dst that the operation may not have, or '' when the
// whole list is free. A name is unavailable either because something already
// stands at it or because the way down to it leaves $dst.
//------------------------------------------------------------------------------
function rtTakenDestination( $files, $dst )
{
	if( !is_array( $files ) || $dst == '' )
		return '';
	$root = rtDestinationRoot( $dst );
	if( $root == '' )
		return rtAddTailSlash( $dst );
	foreach( $files as $file )
	{
		$dest = rtContainedPath( $root, $file );
		if( $dest == '' )
			return rtAddTailSlash( $dst ).( is_string( $file ) ? $file : '' );
		if( rtNameInUse( $dest ) )
			return $dest;
	}
	return '';
}

//------------------------------------------------------------------------------
// Put back, newest first, what a failed rtOpFiles() had already done. False if
// anything could not be put back.
//------------------------------------------------------------------------------
function rtUndoOpFiles( $journal, $dbg = false )
{
	$whole = true;
	foreach( array_reverse( $journal ) as $entry )
	{
		switch( $entry[0] )
		{
			case 'moveback':
			{
				if( !rtMoveFile( $entry[1], $entry[2], $dbg ) )
				{
					if( $dbg ) rtDbg( __FUNCTION__, "can't carry ".$entry[1]." back to ".$entry[2] );
					$whole = false;
				}
				break;
			}
			case 'unlink':
			{
				if( !@unlink( $entry[1] ) )
				{
					if( $dbg ) rtDbg( __FUNCTION__, "can't remove ".$entry[1] );
					$whole = false;
				}
				break;
			}
			default:
			{
				// A directory this operation made. Something else may have put
				// a file in it since, and then it is no longer this
				// operation's to remove.
				@rmdir( $entry[1] );
				break;
			}
		}
	}
	return $whole;
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
	if( !rtMkDir( dirname( rtRemoveTailSlash( $dst ) ), 0777 ) )
	{
		if( $dbg ) rtDbg( __FUNCTION__, "can't create ".dirname( $dst ) );
		return false;
	}

	// Everything below is named from here down, and measured against here.
	$root = rtDestinationRoot( $dst );
	if( $root == '' )
	{
		if( $dbg ) rtDbg( __FUNCTION__, "can't resolve ".$dst );
		return false;
	}
	$dst = rtAddTailSlash( $root );

	// Check if source and destination directories are the same
	if( realpath( $src ) === $root )
	{
		if( $dbg ) rtDbg( __FUNCTION__, "source is equal to destination" );
		if( $dbg ) rtDbg( __FUNCTION__, "( ".$root." )" );
		return false;
	}

	// What this operation has changed so far, so that stopping part way
	// through can be undone. Without it a refusal in the middle leaves the
	// download split between two directories with nothing to say where the
	// other half went, and every caller reads false as "the operation did not
	// happen".
	$journal = array();

	// The root itself. Its parent was made above; this one is made here so
	// that a refusal can take it away again.
	if( !is_dir( $root ) )
	{
		if( !@mkdir( $root, 0777 ) && !is_dir( $root ) )
		{
			if( $dbg ) rtDbg( __FUNCTION__, "can't create ".$root );
			return false;
		}
		$journal[] = array( 'rmdir', $root );
	}

	// Everything below is measured from the root, so the root has to be what
	// it was resolved to be. Anything else means a symlink stands somewhere in
	// it that was not there when it was resolved.
	if( realpath( $root ) !== $root )
	{
		if( $dbg ) rtDbg( __FUNCTION__, "refused, ".$root." is no longer its own path" );
		rtUndoOpFiles( $journal, $dbg );
		return false;
	}

	// Every name is settled before the first file is carried, and settled
	// again below immediately before each one is used: a name that was free
	// when the walk started can be taken by the time it is reached, and a
	// directory on the way down can have become a symlink in the same window.
	$taken = rtTakenDestination( $files, $root );
	if( $taken != '' )
	{
		if( $dbg ) rtDbg( __FUNCTION__, "refused, destination not available: ".$taken );
		rtUndoOpFiles( $journal, $dbg );
		return false;
	}

	$failed = '';
	foreach( $files as $file )
	{
		$source = $src.$file;
		$dest = rtContainedPath( $root, $file, true, $journal );
		if( $dest == '' )
			$failed = "can't reach ".$dst.$file;
		elseif( rtNameInUse( $dest ) )
			$failed = "destination already exists: ".$dest;
		else switch( $op )
		{
			case "HardLink":
			{
				if( link( $source, $dest ) )
				{
					$journal[] = array( 'unlink', $dest );
					break;
				}
			}
			case "Copy":
			{
				if( !copy( $source, $dest ) )
				{
					$failed = "can't copy ".$source;
					break;
				}
				$journal[] = array( 'unlink', $dest );
				break;
			}
			case "SoftLink":
			{
				if( !symlink( $source, $dest ) )
				{
					$failed = "can't link ".$dest;
					break;
				}
				$journal[] = array( 'unlink', $dest );
				break;
			}
			default:
			{
				if( !rtMoveFile( $source, $dest, $dbg ) )
				{
					$failed = "can't move ".$source;
					break;
				}
				$journal[] = array( 'moveback', $dest, $source );
				break;
			}
		}
		if( $failed != '' )
			break;
	}

	if( $failed == '' )
	{
		if( $dbg ) rtDbg( __FUNCTION__, "finished" );
		return true;
	}

	if( $dbg ) rtDbg( __FUNCTION__, "refused, ".$failed );
	if( !rtUndoOpFiles( $journal, $dbg ) )
		if( $dbg ) rtDbg( __FUNCTION__, "and it could not be undone in full" );
	return false;
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
		$handle = @opendir( $path.$subdir );
		if( $handle !== false )
		{
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
	$empty = true;
	$handle = @opendir( $path );
	if($handle !== false)
	{
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
				if( !$with_files || !@unlink( $path_to_item ) )
					$empty = false;
			}
		}
		closedir( $handle );
	}
	return ( $empty && @rmdir( $path ) );
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
