<?php
require_once( 'util.php' );
require_once( '../plugins/create/conf.php');

if($useExternal!==false)
{
	if($do_diagnostic && empty($pathToCreatetorrent) && (findEXE($useExternal)==false))
	{
		$jResult.="plugin.disable();";
		$jEnd.="plugin.showError('WUILang.createExternalNotFound+\' (".$useExternal.").\'');";
	}
	else
		if($useExternal === "transmissioncli")
			$jResult.="plugin.hidePieceSize = true;";
}

$theSettings->registerPlugin("create");
?>
