<?php

$req = new rXMLRPCRequest(array(
	rTorrentSettings::get()->getOnInsertCommand(array('thistory'.User::getUser(), getCmd('cat='))),
	rTorrentSettings::get()->getOnFinishedCommand(array('thistory'.User::getUser(), getCmd('cat='))),
	rTorrentSettings::get()->getOnEraseCommand(array('thistory'.User::getUser(), getCmd('cat=')))
	));
$req->run();
