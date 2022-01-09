<?php

$req = new rXMLRPCRequest( rTorrentSettings::get()->getOnInsertCommand(array('_throttle'.User::getUser(), getCmd('cat='))) );
$req->run();
