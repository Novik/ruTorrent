<?php

require_once( 'util.php' );

$fname = getSettingsPath()."/uisettings.json";
$s = @file_get_contents($fname);
if($s==false)
	$s = '{}';
cachedEcho($s,"application/json",true);
