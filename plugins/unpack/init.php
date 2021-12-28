<?php

eval(getPluginConf($plugin["name"]));
require_once( 'unpack.php' );

if($do_diagnostic)
{
	if(USE_UNZIP)
		$remoteRequests->findRemoteEXE('unzip',"thePlugins.get('unpack').showError('theUILang.unzipNotFound');");
	if(USE_UNRAR)
		$remoteRequests->findRemoteEXE('unrar',"thePlugins.get('unpack').showError('theUILang.unrarNotFound');");
}
if(USE_UNZIP || USE_UNRAR)
{
	$up = rUnpack::load();
	if($up->setHandlers())
	{
		$ep->fetchExternalPath('unzip');
		$ep->fetchExternalPath('unrar');
		
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
