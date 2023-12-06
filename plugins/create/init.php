<?php

eval(FileUtil::getPluginConf($plugin["name"]));

if($useExternal!==false)
{
	if($do_diagnostic && empty($pathToCreatetorrent) && (findEXE($useExternal)==false))
	{
		$jResult.="plugin.disable(); plugin.showError('theUILang.createExternalNotFound+\' (".$useExternal.").\'');";
	}
	else
	{
		if(($useExternal === "transmissioncli") || ($useExternal === "transmissioncreate"))
			$jResult.="plugin.hidePieceSize = true;";

		if (($useExternal !== "torrenttools"))
			$jResult.="plugin.hideHybrid = true;";

		$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
	}
}
else
	$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
