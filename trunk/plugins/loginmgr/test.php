<?php
require_once( "accounts.php" );

$em = accountManager::load();
if($em===false)
{
	$em = new accountManager();
	$em->obtain("./accounts");
}

$client = new Snoopy();
$acc = $em->getAccount("http://hd-dream.net/browse.php");
if($acc)
{
	$em->fetch( $acc, $client, "http://hd-dream.net/browse.php" );
	toLog($client->results);
}

?>