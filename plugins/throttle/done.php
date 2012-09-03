<?php

$req = new rXMLRPCRequest( rTorrentSettings::get()->getOnInsertCommand(array('_throttle'.getUser(), getCmd('cat='))) );
$req->run();
