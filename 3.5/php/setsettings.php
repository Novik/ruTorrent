<?php

require_once( 'util.php' );

if(isset($_REQUEST['v']))
{
	$name = getSettingsPath()."/uisettings.json";
	$fp = fopen( $name.'.tmp', "a" );
	if(flock( $fp, LOCK_EX ))
	{
		ftruncate( $fp, 0 );
		fputs( $fp, $_REQUEST['v'] );
        	fflush( $fp );
		flock( $fp, LOCK_UN );
                fclose( $fp );
       		rename( $name.'.tmp', $name );
	}
}
