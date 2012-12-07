<?php
eval(getPluginConf($plugin["name"]));

$st = getSettingsPath();
makeDirectory( array($st.'/rss',$st.'/rss/cache') );
$needStart = isLocalMode() && $theSettings->linkExist;
if($needStart)
{
	require_once($rootPath.'/plugins/rss/rss.php');
	$mngr = new rRSSManager();
	if($mngr->setHandlers())
	{
		$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
	}
	else
		$jResult .= "plugin.disable(); noty('rss: '+theUILang.pluginCantStart,'error');";
}
else
	$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
