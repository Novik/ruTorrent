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
			$str = $_REQUEST['v'];
			if( (fputs( $fp, $str ) == strlen($str)) && fflush( $fp ) )
			{
				flock( $fp, LOCK_UN );
				if( fclose( $fp ) !== false )
	       				@rename( $name.'.tmp', $name );
				else	       				
					@unlink( $name.'.tmp' );
			}
			else
			{
				flock( $fp, LOCK_UN );
				fclose( $fp );
				@unlink( $name.'.tmp' );
			}
		}
		else
	               	fclose( $fp );
	}
}
