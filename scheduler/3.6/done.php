<?php

$req = new rXMLRPCRequest( rTorrentSettings::get()->getRemoveScheduleCommand('scheduler') );
$req->run();
