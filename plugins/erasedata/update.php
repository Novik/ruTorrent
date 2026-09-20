<?php

if( count( $argv ) > 1 )
	$_SERVER['REMOTE_USER'] = $argv[1];

require_once( dirname(__FILE__)."/../../php/util.php" );
eval(FileUtil::getPluginConf('erasedata'));

function eLog( $str )
{
	global $erasedebug_enabled;
	if($erasedebug_enabled)
		FileUtil::toLog( "erasedata: ".$str );
}

function sortByLevel( $a, $b )
{
	return( strrpos($b,"/")-strrpos($a,"/") );
}

// The base path is the download's own root directory, or, for a single-file
// download, the file itself. Everything else in the list is measured against
// it, and with force deletion it is removed whole, so a value that is not a
// usable base path stops the item rather than being worked around. "/" is not
// one: a list claiming it would put every path on the host inside the download.
function isUsableBasePath($base)
{
	$base = rtrim($base,'/');
	return(($base !== '') && ($base[0] == '/') &&
		(strpos($base,'/../') === false) && (substr($base,-3) != '/..'));
}

// The base as it really is on disk. Everything else in the list is measured
// against this, so a symlink above the download -- a downloads directory
// pointing into a storage pool, which is the usual layout -- is resolved once
// here instead of being walked through for every entry.
//
// The base itself may not be a symlink. With force deletion it is removed
// whole, and a link is a name that belongs to whatever it points at.
function resolveBasePath($base)
{
	$base = rtrim($base,'/');
	if(($base === '') || is_link($base))
		return('');
	$real = realpath($base);
	if($real === false)
		return('');
	$real = rtrim($real,'/');
	return($real === '' ? '' : $real);
}

// The path $file names under the download, resolved against $real_base, or ''
// when it cannot be reached without leaving the download.
//
// A list entry names a file to unlink, and what it names came from the torrent
// -- from path elements its publisher chose. Every file of a download lies
// under that download's base path, so an entry that does not lie under it is
// not a file of this download and is not unlinked.
//
// Lying under it is not a question about the text of the path. unlink() and
// rmdir() are handed the path as one string and follow every symlink in it, so
// each element of the way down is checked with lstat(), which reports a
// symlink as a symlink instead of as whatever it points at. Without that, one
// directory of the download being a link is a name inside the download
// deciding what is deleted outside it.
//
// This is checked here rather than only where the list is written because a
// list already queued when this collector replaces the previous one is read by
// this code, and its entries are as suspect as any: the writer that produced
// them did not refuse a path carrying a line break.
function containedPathUnderBase($file, $base, $real_base)
{
	$base = rtrim($base,'/');
	$real_base = rtrim($real_base,'/');
	if(($base === '') || ($real_base === ''))
		return('');
	if($file === $base)
		return($real_base);
	if(strncmp($file,$base.'/',strlen($base)+1) !== 0)
		return('');
	$parts = explode('/', substr($file,strlen($base)+1));
	$leaf = array_pop($parts);
	if(($leaf === '') || ($leaf === '.') || ($leaf === '..'))
		return('');
	$path = $real_base;
	foreach($parts as $part)
	{
		if(($part === '') || ($part === '.') || ($part === '..'))
			return('');
		$path .= '/'.$part;
		$st = @lstat($path);
		// Not there, so no file of the download is below it either.
		if($st === false)
			return('');
		if(($st['mode'] & 0170000) != 0040000)
			return('');
	}
	return($path.'/'.$leaf);
}

function parseOneItem($item)
{
	global $enableForceDeletion;
	eLog('*** Parse item '.$item);
	$lines = file($item,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
	$cnt = count($lines);
	if($cnt<=3)
		return;
	$dirs = array();
	$force_delete = (intval($lines[$cnt-1]) == 2) && $enableForceDeletion;
	$is_multi = intval($lines[$cnt-2]);
	$base_path = $lines[$cnt-3];
	unset($lines[$cnt-3]);
	unset($lines[$cnt-2]);
	unset($lines[$cnt-1]);
	if(!isUsableBasePath($base_path))
	{
		eLog('REFUSED, not a usable base path: '.$base_path);
		return;
	}
	$real_base = resolveBasePath($base_path);
	if($real_base === '')
	{
		eLog('REFUSED, base path does not resolve to a path of its own: '.$base_path);
		return;
	}

	// Every entry is resolved before anything is deleted, force deletion
	// included. Force deletion removes the base whole and never visits the
	// entries, so this is also what ties the base the list declares to the
	// files it declares: a list whose entries are not files of its own base is
	// not the list of one download, and none of it is acted on.
	$files = array();
	foreach($lines as $file)
	{
		$path = containedPathUnderBase($file,$base_path,$real_base);
		if($path !== '')
		{
			$files[] = $path;
			continue;
		}
		eLog('REFUSED, not under '.$base_path.': '.$file);
		if($force_delete)
		{
			eLog('REFUSED, force delete of '.$base_path.' abandoned');
			return;
		}
	}
	if($force_delete && !count($files))
	{
		eLog('REFUSED, force delete of '.$base_path.' names no files of its own');
		return;
	}

	if( !$force_delete || !$is_multi )
	{
		foreach( $files as $file )
		{
			if(@unlink($file))
				eLog('Successfully delete file '.$file);
			else
				eLog('FAIL Delete file '.$file);
			if($is_multi && (strlen($file) > strlen($real_base)+1))
			{
				$dir = $real_base;
				$pieces = explode('/', substr($file, strlen($real_base)+1));
				for( $i=0; $i<count($pieces)-1; $i++)
				{
					$dir.='/';
					$dir.=$pieces[$i];
					$dirs[] = $dir;
				}
			}
		}
	}
	if($is_multi)
	{
		if($force_delete)
		{
			if(@deleteDir($real_base))
				eLog('Successfully forced delete dir '.$real_base);
			else
				eLog('FAIL force delete dir '.$real_base);
		}
		else
		{
			$dirs = array_unique($dirs);
			usort( $dirs, "sortByLevel" );
			foreach( $dirs as $dir )
				if(@rmdir($dir))
					eLog('Successfully delete dir '.$dir);
				else
					eLog('FAIL delete dir '.$dir);
			if(@rmdir($real_base))
				eLog('Successfully delete dir '.$real_base);
			else
				eLog('FAIL delete dir '.$real_base);
		}
	}
}

define('MAX_DURATION_OF_CHECK',3600);

$listPath = FileUtil::getSettingsPath()."/erasedata";
@FileUtil::makeDirectory($listPath);
$lock = $listPath.'/scheduler.lock';
if(!is_file($lock) || (time()-filemtime($lock)>MAX_DURATION_OF_CHECK))
{
	touch($lock);
       	$list = array();
	if($handle = @opendir($listPath))
	{
	        while(false !== ($file = readdir($handle)))
		{
			$fname = $listPath.'/'.$file;
			if($file != "." && $file != ".." && is_file($fname) && (pathinfo($file,PATHINFO_EXTENSION)=="list") )
				$list[] = $fname;
		}
		closedir($handle);
	}
	foreach( $list as $item )
	{
		parseOneItem($item);
		unlink($item);
	}
	unlink($lock);
}
else
	eLog('Busy, wait for next time.');

function deleteDir($dir)
{
	if(empty($dir))
		return(false);
	// lstat(), so a symlink is seen as a symlink. is_dir() follows one, and
	// recursing through it empties whatever it points at: one directory of the
	// download being a link then decides what is deleted outside the download.
	// A link is removed as the name it is, and what it points at is left.
	$st = @lstat($dir);
	if($st === false)
		return(false);
	if(($st['mode'] & 0170000) != 0040000)
		return(@unlink($dir));
	$files = @scandir($dir);
	if($files === false)
		return(false);
	$empty = true;
	foreach(array_diff($files, array('.','..')) as $file)
	{
		$path = $dir.'/'.$file;
		$s = @lstat($path);
		if(($s !== false) && (($s['mode'] & 0170000) == 0040000))
		{
			if(!deleteDir($path))
				$empty = false;
		}
		elseif(!@unlink($path))
			$empty = false;
	}
	$gone = @rmdir($dir);
	return($gone && $empty);
}
