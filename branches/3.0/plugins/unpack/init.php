<?php
eval(getPluginConf('unpack'));

if($do_diagnostic)
{
	if(USE_UNRAR && (!$pathToUnzip || ($pathToUnzip=="")))
		findRemoteEXE('unzip',"thePlugins.get('unpack').showError('theUILang.unzipNotFound');",$remoteRequests);
	if(USE_UNZIP && (!$pathToUnrar || ($pathToUnrar=="")))
		findRemoteEXE('unrar',"thePlugins.get('unpack').showError('theUILang.unrarNotFound');",$remoteRequests);
}
if(USE_UNZIP || USE_UNRAR)
{
	$theSettings->registerPlugin("unpack");
	$jResult .= ("plugin.useUnzip = ".(USE_UNZIP ? "true;" : "false;"));
	$jResult .= ("plugin.useUnrar = ".(USE_UNRAR ? "true;" : "false;"));
}
else
	$jResult .= "plugin.disable();";

?>
