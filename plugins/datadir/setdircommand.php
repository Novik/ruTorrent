<?php

// The command line action.php hands to sh -c to start setdir.php in the
// background.
//
// It lives on its own, with no dependencies, because what is quoted in it and
// what is not is the whole of its content: seven values go to a shell, and one
// of them used to go unquoted while its six neighbours were escaped.
if( !function_exists( 'rtSetDirCommand' ) )
{
	function rtSetDirCommand( $php, $script, $hash, $datadir,
		$addPath, $moveFiles, $fastResume, $user )
	{
		return(
			escapeshellarg( (string)$php )." ".
			escapeshellarg( (string)$script )." ".
			escapeshellarg( (string)$hash )." ".
			escapeshellarg( (string)$datadir )." ".
			escapeshellarg( (string)$addPath )." ".
			escapeshellarg( (string)$moveFiles )." ".
			escapeshellarg( (string)$fastResume )." ".
			escapeshellarg( (string)$user )." & exit 0" );
	}
}
