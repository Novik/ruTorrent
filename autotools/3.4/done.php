<?php

$req = new rXMLRPCRequest( array( 
	rTorrentSettings::get()->getOnInsertCommand(array('_autolabel'.getUser(), getCmd('cat='))),
	rTorrentSettings::get()->getOnFinishedCommand(array('automove'.getUser(), getCmd('cat='))),
	new rXMLRPCCommand('schedule_remove', 'autowatch'.getUser())
	));
$req->run();

?>