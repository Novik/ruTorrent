<?php

$req = new rXMLRPCRequest( array(
	new rXMLRPCCommand("schedule_remove", "ratio".getUser()),
	rTorrentSettings::get()->getOnInsertCommand(array('_ratio'.getUser(), getCmd('cat=')))
	));
$req->run();

?>