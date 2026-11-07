<?php

$req = new rXMLRPCRequest([
    rTorrentSettings::get()->getOnFinishedCommand(["seedingtime" . User::getUser(),getCmd('cat=')]),
    rTorrentSettings::get()->getOnInsertCommand(["addtime" . User::getUser(),getCmd('cat=')]),
    rTorrentSettings::get()->getOnHashdoneCommand(["seedingtimecheck" . User::getUser(),getCmd('cat=')]),
]);
$req->run();
