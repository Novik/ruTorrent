<?php

$req = new rXMLRPCRequest( array(
		rTorrentSettings::get()->getOnFinishedCommand(array("seedingtime".User::getUser(),getCmd('cat='))),
		rTorrentSettings::get()->getOnInsertCommand(array("addtime".User::getUser(),getCmd('cat='))),
		rTorrentSettings::get()->getOnHashdoneCommand(array("seedingtimecheck".User::getUser(),getCmd('cat=')))
		));
$req->run();
