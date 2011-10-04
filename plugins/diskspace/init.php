<?php

if(!function_exists('disk_total_space') || !function_exists('disk_free_space') ||
	(disk_total_space($topDirectory)===false) || (disk_free_space($topDirectory)===false))
	$jResult .= "plugin.disable();";
else
	$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);

?>