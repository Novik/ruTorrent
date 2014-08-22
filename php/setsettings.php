<?php

require_once( 'util.php' );

if(isset($_REQUEST['v']))
{
	$name = getSettingsPath()."/uisettings.json";
	$fp = fopen( $name.'.tmp', "a" );
	if($fp!==false)
	{
		if(flock( $fp, LOCK_EX ))
		{
			ftruncate( $fp, 0 );
			fputs( $fp, $_REQUEST['v'] );
        		fflush( $fp );
			flock( $fp, LOCK_UN );
       			rename( $name.'.tmp', $name );
		}
               	fclose( $fp );
	}
}
