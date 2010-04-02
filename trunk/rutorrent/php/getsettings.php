<?php

require_once( 'util.php' );

$fname = getSettingsPath()."/uisettings.json";
$s = @file_get_contents($fname);
if($s==false)
	$s = '{}';
else
	$mtime = filemtime($fname);
cachedEcho($s,"application/json",$mtime);

?>
