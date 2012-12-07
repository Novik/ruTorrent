<?php

$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);

require_once( "accounts.php" );

$em = accountManager::load();
if($em===false)
	$em = new accountManager();
$em->obtain();
$jResult.=$em->get();
