<?php

$req = new rXMLRPCRequest([
    rTorrentSettings::get()->getOnInsertCommand(['tadd_trackers1' . User::getUser(), getCmd('cat=')]),
    rTorrentSettings::get()->getOnInsertCommand(['tadd_trackers2' . User::getUser(), getCmd('cat=')]),
]);
$req->run();
