<?php

$req = new rXMLRPCRequest( rTorrentSettings::get()->getOnFinishedCommand(array("unpack".getUser(),getCmd('cat='))) );
$req->run();
