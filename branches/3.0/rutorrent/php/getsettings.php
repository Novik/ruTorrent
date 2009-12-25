<?php

require_once( 'util.php' );

$filename = getSettingsPath()."/uisettings.json";
$s = '{}';
if( is_readable($filename) )
{
	$w = @fopen($filename, "rb");
	if($w)
	{
    	    $s = fgets($w);
	    fclose($w);
	}	    
}
header("Content-Length: ".strlen($s));
header("Content-Type: application/json; charset=UTF-8");
echo $s;
?>
