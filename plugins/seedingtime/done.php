<?php

$req = new rXMLRPCRequest( array(
		rTorrentSettings::get()->getOnInsertCommand(array("addtime".User::getUser(),getCmd('cat='))),
		));
$req->run();
