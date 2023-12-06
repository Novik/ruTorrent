<?php

require_once( '../plugins/xmpp/xmpp.php');

$at = rXmpp::load();
if($at->setHandlers())
{	
	$jResult .= $at->get();
	/*if( $do_diagnostic )
	{
		if( $at->enable_move )
		{
			$path_to_finished = trim( $at->path_to_finished );
			if(!rTorrentSettings::get()->correctDirectory($path_to_finished))
				$path_to_finished = '';
			if( $path_to_finished == '' )
				$jResult .= "plugin.showError('theUILang.autotoolsNoPathToFinished');";
			else
			{
				$session = rTorrentSettings::get()->session;
				if( !strlen($session) || !@file_exists(FileUtil::addslash(rTorrentSettings::get()->session).'.') )
					$jResult .= "plugin.disable(); noty('".$plugin["name"].": '+theUILang.webBadSessionWarning+' (".$session.").','error');";
			}
		}
		if( $at->enable_watch )
		{
			$path_to_watch = trim( $at->path_to_watch );
			if(!rTorrentSettings::get()->correctDirectory($path_to_watch))
				$path_to_watch = '';
			if( $path_to_watch == '' )
				$jResult .= "plugin.showError('theUILang.autotoolsNoPathToWatch');";
		}
	}*/
	$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
}
else
        $jResult .= "plugin.disable(); noty('xmpp: '+theUILang.pluginCantStart,'error');";
