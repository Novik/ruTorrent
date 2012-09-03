<?php

$req = new rXMLRPCRequest( array(
	rTorrentSettings::get()->getRemoveScheduleCommand("ratio"),
	rTorrentSettings::get()->getOnInsertCommand(array('_ratio'.getUser(), getCmd('cat=')))
	));
$req->run();
