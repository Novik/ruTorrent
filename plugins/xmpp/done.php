<?php

$req = new rXMLRPCRequest( array( 
	rTorrentSettings::get()->getOnFinishedCommand(array('xmpp'.User::getUser(), getCmd('cat=')))
	));
$req->run();
