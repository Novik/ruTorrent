<?php
eval(FileUtil::getPluginConf($plugin["name"]));

$st = FileUtil::getSettingsPath();
FileUtil::makeDirectory( array($st.'/rss',$st.'/rss/cache') );
$needStart = User::isLocalMode() && $theSettings->linkExist;
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
