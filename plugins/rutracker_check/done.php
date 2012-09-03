<?php

$req = new rXMLRPCRequest( rTorrentSettings::get()->getRemoveScheduleCommand('rutracker_check') );
$req->run();
