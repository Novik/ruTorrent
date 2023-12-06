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

function parseOneItem($item)
{
	global $enableForceDeletion;
	eLog('*** Parse item '.$item);
	$lines = file($item,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
	$cnt = count($lines);
	if($cnt>3)
	{
		$dirs = array();
		$force_delete = (intval($lines[$cnt-1]) == 2) && $enableForceDeletion;
		$is_multi = intval($lines[$cnt-2]);
		$base_path = $lines[$cnt-3];
		unset($lines[$cnt-3]);
		unset($lines[$cnt-2]);
		unset($lines[$cnt-1]);
		if( !$force_delete || !$is_multi )
		{
			foreach( $lines as $file )
			{
				if(@unlink($file))
					eLog('Successfully delete file '.$file);
				else
					eLog('FAIL Delete file '.$file);
				if($is_multi)
				{
					$dir = $base_path;
					$file = substr($file, strlen($base_path)+1);
					$pieces = explode('/', $file);
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
				if(@deleteDir($base_path))
					eLog('Successfully forced delete dir '.$base_path);
				else
					eLog('FAIL force delete dir '.$base_path);
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
				if(@rmdir($base_path))
					eLog('Successfully delete dir '.$base_path);
				else
					eLog('FAIL delete dir '.$base_path);
			}
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
	$files = array_diff(scandir($dir), array('.','..'));
	foreach($files as $file)
	{
		$path = $dir.'/'.$file;
		(is_dir($path)) ? deleteDir($path) : @unlink($path);
	}
	return(@rmdir($dir));
}