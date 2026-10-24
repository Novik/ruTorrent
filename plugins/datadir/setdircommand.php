<?php

require_once(__DIR__.'/../../php/utility/utility.php');

// The command line action.php hands to sh -c to start setdir.php in the
// background.
//
// Every argument uses the shared byte-preserving shell quote owner.
if( !function_exists( 'rtSetDirCommand' ) )
{
	function rtSetDirCommand( $php, $script, $hash, $datadir,
		$addPath, $moveFiles, $fastResume, $user )
	{
		return(
			Utility::escapeShellArg( (string)$php )." ".
			Utility::escapeShellArg( (string)$script )." ".
			Utility::escapeShellArg( (string)$hash )." ".
			Utility::escapeShellArg( (string)$datadir )." ".
			Utility::escapeShellArg( (string)$addPath )." ".
			Utility::escapeShellArg( (string)$moveFiles )." ".
			Utility::escapeShellArg( (string)$fastResume )." ".
			Utility::escapeShellArg( (string)$user )." & exit 0" );
	}
}
