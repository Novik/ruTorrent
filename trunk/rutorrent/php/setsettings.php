<?php

require_once( 'util.php' );

$filename = getSettingsPath()."/uisettings.json";
if($w = @fopen($filename, "wb"))
{
	if(isset($_REQUEST['v']))
		fputs($w,$_REQUEST['v']);
	fclose($w);
}

?>