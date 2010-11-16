<?php

$theSettings->registerPlugin("loginmgr");

require_once( "accounts.php" );

$em = accountManager::load();
if($em===false)
	$em = new accountManager();
$em->obtain();
$jResult.=$em->get();

?>