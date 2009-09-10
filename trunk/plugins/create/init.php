<?php
require_once( 'util.php' );
require_once( 'plugins/create/conf.php');

if($useExternal!==false)
{
	if(DO_DIAGNOSTIC && (findEXE($useExternal)==false))
		$jResult.="utWebUI.showCreateError('WUILang.createExternalNotFound+\' (".$useExternal.").\'');";
	if($useExternal === "transmissioncli")
		$jResult.="utWebUI.hidePieceSize = true;";
}

$theSettings->registerPlugin("create");
?>
