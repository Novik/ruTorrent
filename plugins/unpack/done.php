<?php

$req = new rXMLRPCRequest(rTorrentSettings::get()->getOnFinishedCommand(["unpack" . User::getUser(),getCmd('cat=')]));
$req->run();
