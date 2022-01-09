<?php

require_once( 'util.php' );

$s = '{}';
$fname = FileUtil::getSettingsPath()."/uisettings.json";
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

CachedEcho::send($s,"application/json",true);
