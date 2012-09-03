<?php

$req = new rXMLRPCRequest( array( 
	rTorrentSettings::get()->getOnInsertCommand(array('_autolabel'.getUser(), getCmd('cat='))),
	rTorrentSettings::get()->getOnFinishedCommand(array('automove'.getUser(), getCmd('cat='))),
	rTorrentSettings::get()->getRemoveScheduleCommand('autowatch')
	));
$req->run();
