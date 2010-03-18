<?php

$req = new rXMLRPCRequest(array(
	$theSettings->getOnInsertCommand(array('add_trackers1'.getUser(), 'cat=')),
	$theSettings->getOnInsertCommand(array('add_trackers2'.getUser(),'cat='))
	));
$req->run();

?>
