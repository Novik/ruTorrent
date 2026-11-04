<?php

$req = new rXMLRPCRequest([
    rTorrentSettings::get()->getOnInsertCommand(['thistory' . User::getUser(), getCmd('cat=')]),
    rTorrentSettings::get()->getOnFinishedCommand(['thistory' . User::getUser(), getCmd('cat=')]),
    rTorrentSettings::get()->getOnEraseCommand(['thistory' . User::getUser(), getCmd('cat=')]),
]);
$req->run();
