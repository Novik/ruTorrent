<?php

eval(getPluginConf($plugin["name"]));
require_once( 'unpack.php' );

if($do_diagnostic)
{
	if(USE_UNRAR)
		findRemoteEXE('unzip',"thePlugins.get('unpack').showError('theUILang.unzipNotFound');",$remoteRequests);
	if(USE_UNZIP)
		findRemoteEXE('unrar',"thePlugins.get('unpack').showError('theUILang.unrarNotFound');",$remoteRequests);
}
if(USE_UNZIP || USE_UNRAR)
{
	$req = new rXMLRPCRequest( 
		$theSettings->getOnFinishedCommand( array('unpack'.getUser(), 
			getCmd('execute').'={'.getPHP().','.$rootPath.'/plugins/unpack/update.php,$'.getCmd('d.get_base_path').
				'=,$'.getCmd('d.get_custom1').'=,$'.getCmd('d.get_name').'=,'.getUser().'}')));
	if($req->run() && !$req->fault)
	{
		$jResult .= ("plugin.useUnzip = ".(USE_UNZIP ? "true;" : "false;"));
		$jResult .= ("plugin.useUnrar = ".(USE_UNRAR ? "true;" : "false;"));
        	$up = rUnpack::load();
		$jResult .= $up->get();
	        $theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
	}
	else
		$jResult .= "plugin.disable(); log('unpack: '+theUILang.pluginCantStart);";
}
else
	$jResult .= "plugin.disable(); log('unpack: '+theUILang.pluginCantStart);";

?>