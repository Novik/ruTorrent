<?php

$req = new rXMLRPCRequest( array( 
	$theSettings->getOnInsertCommand(array('autolabel'.getUser(), 'cat=')),
	$theSettings->getOnFinishedCommand(array('automove'.getUser(), 'cat=')),
	new rXMLRPCCommand('schedule_remove', 'autowatch'.getUser())
	));
$req->run();

?>