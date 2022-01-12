<?php

if( chdir( dirname( __FILE__) ) )
{
	set_time_limit(0);
	if( count( $argv ) > 8 )
		$_SERVER['REMOTE_USER'] = $argv[8];
	if( count( $argv ) > 7 )
	{
		require_once( "unpack.php" );
		$up = rUnpack::load();
		if($up->enabled)
		{
			$dir_name = ($argv[7]=='') ? $argv[1] : $argv[7];
			$base_name = (intval($argv[3]) ? $dir_name : FileUtil::addslash($dir_name).$argv[2]);
			$download_name = (intval($argv[3]) ? $argv[1] : FileUtil::addslash($argv[1]).$argv[2]);
			$label = rawurldecode($argv[4]);
			if(@preg_match($up->filter.'u',$label)==1)
				$up->startSilentTask($base_name,$download_name,$label,$argv[5],$argv[6]);
		}
	}
}
