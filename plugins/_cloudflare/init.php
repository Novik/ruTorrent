<?php

require_once( dirname(__FILE__)."/cloudflare.php" );

$ok = true;
if($do_diagnostic)
{
	if( !rCloudflare::is_module_present() )
	{
		$jResult .= "plugin.disable(); plugin.showError('theUILang.cannotLoadCloudscraper');";
		$ok = false;
	}
}
if($ok)
{
	$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
	$theSettings->registerEventHook($plugin["name"],"URLFetched");
}
