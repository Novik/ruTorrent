<?php

$req = new rXMLRPCRequest(array(
	rTorrentSettings::get()->getOnInsertCommand(array('_exratio1'.getUser(), getCmd('cat='))),
	rTorrentSettings::get()->getOnInsertCommand(array('_exratio2'.getUser(), getCmd('cat=')))
	));
$req->run();
rTorrentSettings::get()->unregisterEventHook("extratio","LabelChanged");
