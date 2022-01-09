<?php

require_once( "xmlrpc.php" );
eval( FileUtil::getPluginConf( $plugin["name"] ) );

$session = rTorrentSettings::get()->session;
if( !strlen($session) || !@file_exists(FileUtil::addslash($session).'.') )
{
	$jResult .= "plugin.disable(); noty('".$plugin["name"].": '+theUILang.webBadSessionError+' (".$session.").','error');";
}
else
{
	if($updateInterval)
	{
		$req = new rXMLRPCRequest( $theSettings->getAbsScheduleCommand('rutracker_check',$updateInterval*60,
			getCmd('execute').'={sh,-c,'.escapeshellarg(Utility::getPHP()).' '.escapeshellarg(dirname(__FILE__).'/update.php').' '.escapeshellarg(User::getUser()).' &}' ));
		if($req->success())
			$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
		else
			$jResult .= "plugin.disable(); noty('rutracker_check: '+theUILang.pluginCantStart, 'error');";
	}
	else
	{
		require( 'done.php' );
		$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
	}
}
