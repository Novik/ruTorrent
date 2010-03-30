<?php

require_once( 'util.php' );

$fname = getSettingsPath()."/uisettings.json";
$s = @file_get_contents($fname);
if($s==false)
	$s = '{}';
else
	$mtime = filemtime($fname);
header("Content-Type: application/json; charset=UTF-8");
cachedEcho($s,$mtime);

?>
