<?php

$req = new rXMLRPCRequest(array(
	rTorrentSettings::get()->getOnInsertCommand(array('tadd_trackers1'.getUser(), getCmd('cat='))),
	rTorrentSettings::get()->getOnInsertCommand(array('tadd_trackers2'.getUser(), getCmd('cat=')))
	));
$req->run();
