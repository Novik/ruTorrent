<?php

$req = new rXMLRPCRequest( array( 
	rTorrentSettings::get()->getOnFinishedCommand(array('xmpp'.getUser(), getCmd('cat=')))
	));
$req->run();
