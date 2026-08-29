<?php

require_once( dirname(__FILE__)."/../../php/xmlrpc.php" );
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
		// getScheduleCommand, not getAbsScheduleCommand: this file runs on every
		// full load of the web interface, and rTorrent's scheduler replaces an
		// entry that reuses a key (CommandScheduler::insert deletes the old item
		// and re-enables the new one at now+start). The absolute form starts its
		// countdown at registration time, so each reload pushed the next check a
		// whole hour away and a user who kept refreshing never got one at all.
		// This form aligns the start to the next wall-clock boundary, which a
		// reload recomputes to the same instant. Same choice as plugins/trafic;
		// note it takes the interval in minutes rather than seconds.
		$req = new rXMLRPCRequest( $theSettings->getScheduleCommand('rutracker_check',$updateInterval,
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
