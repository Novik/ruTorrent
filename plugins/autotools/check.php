<?php

if( !chdir( dirname( __FILE__) ) )
	exit();

if( count( $argv ) > 6 )
	$_SERVER['REMOTE_USER'] = $argv[6];

require_once( "./util_rt.php" );
require_once( "./autotools.php" );

$base_path = $argv[1];
$base_name = $argv[2];
$is_multy = $argv[3];
$label	   = rawurldecode($argv[4]);
$name = $argv[5];

$base_path = rtRemoveTailSlash( $base_path );
$base_path = rtRemoveLastToken( $base_path, '/' );	// filename or dirname
$base_path = rtAddTailSlash( $base_path );
$dest_path = $base_path;
$at = rAutoTools::load();
if( $at->enable_move && (@preg_match($at->automove_filter.'u',$label)==1) )
{
	$path_to_finished = trim( $at->path_to_finished );
	if( $path_to_finished != '' )
	{
		$path_to_finished = rtAddTailSlash( $path_to_finished );
		$directory    = rTorrentSettings::get()->directory;
		if(!empty($directory))
		{
			$directory = rtAddTailSlash( $directory );
			$rel_path = rtGetRelativePath( $directory, $base_path );
			//------------------------------------------------------------------------------
			// !! this is a feature !!
			// ($rel_path == '') means, that $base_path is NOT a SUBDIR of $directory at all
			// so, we have to skip all automove actions
			// for example, if we don't want torrent to be automoved - we save it out of $directory subtree
			//------------------------------------------------------------------------------
			if( $rel_path != '' )
			{
				if( $rel_path == './' ) $rel_path = '';
				$dest_path = rtAddTailSlash( $path_to_finished.$rel_path );
				if($at->addLabel && ($label!=''))
	        			$dest_path.=addslash($label);
		        	if($at->addName && ($name!=''))
					$dest_path.=addslash($name);
			}
		}
	}
}

if( $is_multy )
	$sub_dir = rtAddTailSlash( $base_name );	// $base_file - is a directory
else
	$sub_dir = '';					// $base_file - is really a file
$dest_path.=$sub_dir;
echo $dest_path;
