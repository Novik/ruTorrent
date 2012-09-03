<?php

$req = new rXMLRPCRequest( array(
		rTorrentSettings::get()->getOnFinishedCommand(array("seedingtime".getUser(),getCmd('cat='))),
		rTorrentSettings::get()->getOnInsertCommand(array("addtime".getUser(),getCmd('cat='))),
		rTorrentSettings::get()->getOnHashdoneCommand(array("seedingtimecheck".getUser(),getCmd('cat=')))
		));
$req->run();
