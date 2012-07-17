<?php

$req = rTorrentSettings::get()->getRemoveScheduleCommand('scheduler');
$req->run();

?>