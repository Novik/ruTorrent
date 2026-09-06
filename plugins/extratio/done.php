<?php

$req = new rXMLRPCRequest([
    rTorrentSettings::get()->getOnInsertCommand(['_exratio1' . User::getUser(), getCmd('cat=')]),
    rTorrentSettings::get()->getOnInsertCommand(['_exratio2' . User::getUser(), getCmd('cat=')]),
]);
$req->run();
rTorrentSettings::get()->unregisterEventHook("extratio", "LabelChanged");
