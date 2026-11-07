<?php

$req = new rXMLRPCRequest([
    rTorrentSettings::get()->getRemoveScheduleCommand("ratio"),
    rTorrentSettings::get()->getOnInsertCommand(['_ratio' . User::getUser(), getCmd('cat=')]),
]);
$req->run();
