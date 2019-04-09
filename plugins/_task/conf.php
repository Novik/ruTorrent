<?php

// configuration parameters

if(empty($pathToExternals['pgrep']))	// May be path already defined?
{
	$pathToExternals['pgrep'] = '';	// Something like /usr/bin/pgrep. If empty, will be found in PATH.
}

$maxConcurentTasks 	= 3;	
$showTabAlways		= 1;	// if 1 then show tab 'Tasks' on the start always. If 0 - then only if background tasks exists