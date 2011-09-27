<?php

$req = new rXMLRPCRequest( array(
	$theSettings->getOnEraseCommand(array('erasedata0'.getUser(), getCmd('cat='))),
	$theSettings->getOnEraseCommand(array('erasedata1'.getUser(), getCmd('cat='))),
	new rXMLRPCCommand('schedule_remove', 'erasedata'.getUser())
	));
$req->run();

?>