<?php

$req = new rXMLRPCRequest(array(
	rTorrentSettings::get()->getOnInsertCommand(array('tadd_trackers1'.User::getUser(), getCmd('cat='))),
	rTorrentSettings::get()->getOnInsertCommand(array('tadd_trackers2'.User::getUser(), getCmd('cat=')))
	));
$req->run();
