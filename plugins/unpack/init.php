<?php

eval(FileUtil::getPluginConf($plugin["name"]));
require_once( 'unpack.php' );

if($do_diagnostic)
{
	if(USE_UNZIP)
	{
		$externelUnzip = $localHostedMode ? findEXE('unzip') : false;
		if ($externelUnzip===false || !FileUtil::getMinFilePerms($externelUnzip))
		{
			$unzipErrorStr = "thePlugins.get('unpack').showError('theUILang.unzipNotFound');";
			findRemoteEXE('unzip',$unzipErrorStr,$remoteRequests);
		}
	}
	if(USE_UNRAR)
	{
		$externelUnrar = $localHostedMode ? findEXE('unrar') : false;
		if ($externelUnrar===false || !FileUtil::getMinFilePerms($externelUnrar))
		{
			$unrarErrorStr = "thePlugins.get('unpack').showError('theUILang.unrarNotFound');";
			findRemoteEXE('unrar',$unrarErrorStr,$remoteRequests);
		}
	}
}
if(USE_UNZIP || USE_UNRAR)
{
	$up = rUnpack::load();
	if($up->setHandlers())
	{
		$jResult .= ("plugin.useUnzip = ".(USE_UNZIP ? "true;" : "false;"));
		$jResult .= ("plugin.useUnrar = ".(USE_UNRAR ? "true;" : "false;"));
		$jResult .= $up->get();
	        $theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
	}
	else
		$jResult .= "plugin.disable(); noty('unpack: '+theUILang.pluginCantStart,'error');";
}
else
	$jResult .= "plugin.disable(); noty('unpack: '+theUILang.pluginCantStart,'error');";
