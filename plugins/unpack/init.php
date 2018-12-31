<?php

eval(getPluginConf($plugin["name"]));
require_once( 'unpack.php' );

if($do_diagnostic)
{
	if(USE_UNRAR)
		findRemoteEXE('unzip',"thePlugins.get('unpack').showError('theUILang.unrarNotFound');",$remoteRequests);
	if(USE_UNZIP)
		findRemoteEXE('unrar',"thePlugins.get('unpack').showError('theUILang.unzipNotFound');",$remoteRequests);
	if(USE_7Z)
		findRemoteEXE('7z',"thePlugins.get('unpack').showError('theUILang.7zNotFound');",$remoteRequests);
}
if(USE_UNZIP || USE_UNRAR || USE_7Z)
{
	$up = rUnpack::load();
	if($up->setHandlers())
	{
		$jResult .= ("plugin.useUnzip = ".(USE_UNZIP ? "true;" : "false;"));
		$jResult .= ("plugin.useUnrar = ".(USE_UNRAR ? "true;" : "false;"));
		$jResult .= ("plugin.use7z = ".(USE_7Z ? "true;" : "false;"));
		$jResult .= $up->get();
	        $theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
	}
	else
		$jResult .= "plugin.disable(); noty('unpack: '+theUILang.pluginCantStart,'error');";
}
else
	$jResult .= "plugin.disable(); noty('unpack: '+theUILang.pluginCantStart,'error');";
