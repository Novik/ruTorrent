<?php

$req = new rXMLRPCRequest( rTorrentSettings::get()->getRemoveScheduleCommand("trafic") );
$req->run();
