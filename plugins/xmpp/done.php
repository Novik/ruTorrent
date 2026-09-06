<?php

$req = new rXMLRPCRequest([
    rTorrentSettings::get()->getOnFinishedCommand(['xmpp' . User::getUser(), getCmd('cat=')]),
]);
$req->run();
