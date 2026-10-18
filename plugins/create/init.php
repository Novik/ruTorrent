<?php

require_once( dirname(__FILE__).'/externals.php' );

eval(FileUtil::getPluginConf($plugin["name"]));

if($useExternal!==false)
{
	$externalEXE = createExternalEXE($useExternal);
	if($do_diagnostic && empty($pathToCreatetorrent) && (findEXE($externalEXE)==false))
	{
		$jResult.="plugin.disable(); plugin.showError('theUILang.createExternalNotFound+\' (".$externalEXE.").\'');";
	}
	else
	{
		if($useExternal === "transmissioncreate")
			$jResult.="plugin.hidePieceSize = true;";

		if (($useExternal !== "torrenttools") && ($useInternalHybrid==false))
			$jResult.="plugin.hideHybrid = true;";

		$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
	}
}
else
	$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
