<?php

$req = new rXMLRPCRequest( array(
	rTorrentSettings::get()->getOnEraseCommand(array('erasedata0'.getUser(), getCmd('cat='))),
	rTorrentSettings::get()->getOnEraseCommand(array('erasedata1'.getUser(), getCmd('cat='))),
	new rXMLRPCCommand('schedule_remove', 'erasedata'.getUser())
	));
$req->run();

?>