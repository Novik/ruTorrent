<?php

$req = new rXMLRPCRequest([
    rTorrentSettings::get()->getRemoveScheduleCommand("erasedata"),
]);
$req->run();
