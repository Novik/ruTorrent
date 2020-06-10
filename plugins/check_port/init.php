<?php

eval(getPluginConf($plugin["name"]));

if($useWebsite!==false)
{
	if(($useWebsite === "yougetsignal") || ($useWebsite === "portchecker"))
		$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
	else
		$jResult.="plugin.disable(); plugin.showError('theUILang.checkWebsiteNotFound+\' (".$useWebsite.").\'');";
}
else
	$jResult.="plugin.disable();";
