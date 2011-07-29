<?php

$req = new rXMLRPCRequest( array(
	new rXMLRPCCommand("schedule_remove", "ratio".getUser()),
	$theSettings->getOnInsertCommand(array('_ratio'.getUser(), getCmd('cat=')))
	));
$req->run();

?>