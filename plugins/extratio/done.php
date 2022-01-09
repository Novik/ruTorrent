<?php

$req = new rXMLRPCRequest(array(
	rTorrentSettings::get()->getOnInsertCommand(array('_exratio1'.User::getUser(), getCmd('cat='))),
	rTorrentSettings::get()->getOnInsertCommand(array('_exratio2'.User::getUser(), getCmd('cat=')))
	));
$req->run();
rTorrentSettings::get()->unregisterEventHook("extratio","LabelChanged");
