<?php

$req = new rXMLRPCRequest( rTorrentSettings::get()->getRemoveScheduleCommand("rss") );
$req->run();
