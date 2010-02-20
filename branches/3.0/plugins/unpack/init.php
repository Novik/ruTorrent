<?php
eval(getPluginConf('unpack'));
require_once( 'unpack.php' );

$needStart = true;
if($do_diagnostic)
{
	if(USE_UNRAR && (!$pathToUnzip || ($pathToUnzip=="")))
		findRemoteEXE('unzip',"thePlugins.get('unpack').showError('theUILang.unzipNotFound');",$remoteRequests);
	if(USE_UNZIP && (!$pathToUnrar || ($pathToUnrar=="")))
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
	if( $theSettings->iVersion < 0x804 )
		$cmdFinished = new rXMLRPCCommand('on_finished');
	else
		$cmdFinished = new rXMLRPCCommand('system.method.set_key','event.download.finished');
	$cmdFinished->addParameters( array('unpack'.getUser(), 'execute={'.getPHP().','.$rootPath.'/plugins/unpack/update.php,$d.get_base_path=,$d.get_custom1=,$d.get_name=,'.getUser().'}') );
	$req = new rXMLRPCRequest( $cmdFinished );
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
