<?php

$req = new rXMLRPCRequest( array(
	rTorrentSettings::get()->getOnEraseCommand(array('erasedata0'.User::getUser(), getCmd('cat='))),
	rTorrentSettings::get()->getOnEraseCommand(array('erasedata1'.User::getUser(), getCmd('cat='))),
	rTorrentSettings::get()->getRemoveScheduleCommand("erasedata")
	));
$req->run();
