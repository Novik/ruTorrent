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
			if( fputs( $fp, $_REQUEST['v'] ) !== false )
			{
	        		fflush( $fp );
				flock( $fp, LOCK_UN );
       				rename( $name.'.tmp', $name );
			}
			else
			{
				flock( $fp, LOCK_UN );
				fclose( $fp );
				unlink( $name.'.tmp' );
				return;
			}       				
		}
               	fclose( $fp );
	}
}
