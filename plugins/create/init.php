<?php

eval(getPluginConf('create'));

if($do_diagnostic)
{
	$sh = array('buildtorrent.sh','createtorrent.sh','inner.sh','mktorrent.sh','transmissioncli.sh');
	foreach($sh as $key=>$file)
	{
        	$fname = $rootPath.'/plugins/create/'.$file;
		@chmod($fname,0755);
		if(!isUserHavePermission($theSettings->uid,$theSettings->gid,$fname,0x0005))
			$jResult.="plugin.showError('theUILang.badScriptPath+\' (".$fname.")\'');";
	}
	findRemoteEXE('pgrep',"thePlugins.get('create').showError('theUILang.pgrepNotFound');",$remoteRequests);
}

if($useExternal!==false)
{
	if($do_diagnostic && empty($pathToCreatetorrent) && (findEXE($useExternal)==false))
		$jResult.="plugin.disable(); plugin.showError('theUILang.createExternalNotFound+\' (".$useExternal.").\'');";
	else
	{
		if($useExternal === "transmissioncli")
			$jResult.="plugin.hidePieceSize = true;";
		$theSettings->registerPlugin("create");
	}
}
else
	$theSettings->registerPlugin("create");

?>