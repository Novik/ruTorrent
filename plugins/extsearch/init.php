<?php

$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);

require_once( "engines.php" );

$em = engineManager::load();
if($em===false)
	$em = new engineManager();
$em->obtain();
$jResult.="plugin.sites = cloneObject(theSearchEngines.sites);";
$jResult.=$em->get();
