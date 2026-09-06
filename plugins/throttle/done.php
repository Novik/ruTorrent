<?php

$req = new rXMLRPCRequest(rTorrentSettings::get()->getOnInsertCommand(['_throttle' . User::getUser(), getCmd('cat=')]));
$req->run();
