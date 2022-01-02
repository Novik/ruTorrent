<?php

$req = new rXMLRPCRequest( rTorrentSettings::get()->getOnFinishedCommand(array("unpack".User::getUser(),getCmd('cat='))) );
$req->run();
