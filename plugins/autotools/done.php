<?php

$req = new rXMLRPCRequest([
    rTorrentSettings::get()->getOnInsertCommand(['_autolabel' . User::getUser(), getCmd('cat=')]),
    rTorrentSettings::get()->getOnFinishedCommand(['automove' . User::getUser(), getCmd('cat=')]),
    rTorrentSettings::get()->getRemoveScheduleCommand('autowatch'),
]);
$req->run();
