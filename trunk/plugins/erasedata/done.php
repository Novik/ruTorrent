<?php

$req = new rXMLRPCRequest( array(
	rTorrentSettings::get()->getOnEraseCommand(array('erasedata0'.getUser(), getCmd('cat='))),
	rTorrentSettings::get()->getOnEraseCommand(array('erasedata1'.getUser(), getCmd('cat='))),
	rTorrentSettings::get()->getRemoveScheduleCommand("erasedata")
	));
$req->run();
