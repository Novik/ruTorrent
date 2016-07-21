<?php
require_once( "accounts.php" );

$em = accountManager::load();
if($em===false)
{
	$em = new accountManager();
	$em->obtain("./accounts");
}

if(isset($_REQUEST['mode']))
{
	$cmd = $_REQUEST['mode'];
	switch($cmd)
	{
		case "set":
		{
			$em->set();
			cachedEcho($em->get(),"application/javascript");
			break;
		}
		case "info":
		{
			cachedEcho(safe_json_encode($em->getInfo()),"application/json");
			break;			
		}
	}
}
