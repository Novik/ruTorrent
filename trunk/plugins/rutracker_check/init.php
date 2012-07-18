<?php

require_once( "xmlrpc.php" );
eval( getPluginConf( $plugin["name"] ) );

$session = rTorrentSettings::get()->session;
if( !strlen($session) || !is_executable(addslash(rTorrentSettings::get()->session)))
{
	$jResult .= "plugin.disable(); log('".$plugin["name"].": '+theUILang.webBadSessionError+' (".$session.").');";
}
else
{
	if($updateInterval)
	{
		$req = new rXMLRPCRequest( $theSettings->getScheduleCommand('rutracker_check',$updateInterval,
			getCmd('execute').'={sh,-c,'.escapeshellarg(getPHP()).' '.escapeshellarg(dirname(__FILE__).'/update.php').' '.escapeshellarg(getUser()).' &}' ));
		if($req->success())
			$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
		else
			$jResult .= "plugin.disable(); log('rutracker_check: '+theUILang.pluginCantStart);";
	}
	else
	{
		require( 'done.php' );
		$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
	}
}

?>