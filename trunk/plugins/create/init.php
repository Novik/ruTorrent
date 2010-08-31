<?php

eval(getPluginConf('create'));

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