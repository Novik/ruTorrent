<?php

$req = new rXMLRPCRequest( array( 
	rTorrentSettings::get()->getOnInsertCommand(array('_autolabel'.User::getUser(), getCmd('cat='))),
	rTorrentSettings::get()->getOnFinishedCommand(array('automove'.User::getUser(), getCmd('cat='))),
	rTorrentSettings::get()->getRemoveScheduleCommand('autowatch')
	));
$req->run();
