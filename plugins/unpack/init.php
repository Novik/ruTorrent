<?php
eval(getPluginConf('unpack'));
require_once( 'unpack.php' );

$needStart = true;
if($do_diagnostic)
{
	if(USE_UNRAR)
		findRemoteEXE('unzip',"thePlugins.get('unpack').showError('theUILang.unzipNotFound');",$remoteRequests);
	if(USE_UNZIP)
		findRemoteEXE('unrar',"thePlugins.get('unpack').showError('theUILang.unrarNotFound');",$remoteRequests);
	$sh = array('unall_dir.sh','unrar_dir.sh','unzip_dir.sh','unrar_file.sh','unzip_file.sh');
	foreach($sh as $key=>$file)
	{
	        $fname = $rootPath.'/plugins/unpack/'.$file;
		@chmod($fname,0755);
		if(!isUserHavePermission($theSettings->uid,$theSettings->gid,$fname,0x0005))
		{
			$jResult.="plugin.disable(); plugin.showError('theUILang.unpackRunNotAvailable+\" (".$fname.")\"');";
			$needStart = false;
		}
	}
	@chmod($rootPath.'/plugins/unpack/update.php',0644);
	if(!isUserHavePermission($theSettings->uid,$theSettings->gid,$rootPath.'/plugins/unpack/update.php',0x0004))
	{
		$jResult.="plugin.disable(); plugin.showError('theUILang.unpackUpdNotAvailable');";
		$needStart = false;
	}
}
if($needStart && (USE_UNZIP || USE_UNRAR))
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
	        $theSettings->registerPlugin("unpack");
	}
	else
		$jResult .= "plugin.disable(); log('unpack: '+theUILang.pluginCantStart);";
}
else
	$jResult .= "plugin.disable(); log('unpack: '+theUILang.pluginCantStart);";

?>
