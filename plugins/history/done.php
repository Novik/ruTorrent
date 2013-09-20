<?php

$req = new rXMLRPCRequest(array(
	rTorrentSettings::get()->getOnInsertCommand(array('thistory'.getUser(), getCmd('cat='))),
	rTorrentSettings::get()->getOnFinishedCommand(array('thistory'.getUser(), getCmd('cat='))),
	rTorrentSettings::get()->getOnEraseCommand(array('thistory'.getUser(), getCmd('cat=')))
	));
$req->run();
