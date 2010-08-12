<?php

$req = new rXMLRPCRequest(array(
	$theSettings->getOnInsertCommand(array('tadd_trackers1'.getUser(), getCmd('cat='))),
	$theSettings->getOnInsertCommand(array('tadd_trackers2'.getUser(), getCmd('cat=')))
	));
$req->run();

?>