<?php
eval(FileUtil::getPluginConf($plugin["name"]));

$st = FileUtil::getSettingsPath();
FileUtil::makeDirectory( array($st.'/rss',$st.'/rss/cache') );
$needStart = User::isLocalMode() && $theSettings->linkExist;
if($needStart)
{
	// Go back to ruTorrent root folder and include rss.php plugin file
	require_once( dirname(__FILE__)."/../../plugins/rss/rss.php");
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
