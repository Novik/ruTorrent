<?php

$req = new rXMLRPCRequest( array(
	rTorrentSettings::get()->getRemoveScheduleCommand("erasedata")
	));
$req->run();
