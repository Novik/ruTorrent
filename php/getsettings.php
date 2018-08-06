<?php

require_once( 'util.php' );

$s = '{}';
$fname = getSettingsPath()."/uisettings.json";
$fo = @fopen($fname, 'r');
if($fo!==false)
{
	if(flock($fo, LOCK_SH))
	{
		$s = @file_get_contents($fname);
		if($s==false)
			$s = '{}';
		flock($fo, LOCK_UN); 
	}
	fclose($fo); 
}

cachedEcho($s,"application/json",true);
