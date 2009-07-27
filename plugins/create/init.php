<?php
require_once( 'util.php' );
require_once( 'plugins/create/conf.php');

if($useExternal)
{
	if(!$pathToCreatetorrent || ($pathToCreatetorrent==""))
	{
		$pathToCreatetorrent = "createtorrent";
		if(DO_DIAGNOSTIC)
			findRemoteEXE('createtorrent',"utWebUI.showCreateError('WUILang.createExternalNotFound');",$remoteRequests);
	}
}
?>
