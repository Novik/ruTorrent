<?php

$req = new rXMLRPCRequest( array( 
	$theSettings->getOnInsertCommand(array('_autolabel'.getUser(), getCmd('cat='))),
	$theSettings->getOnFinishedCommand(array('automove'.getUser(), getCmd('cat='))),
	new rXMLRPCCommand('schedule_remove', 'autowatch'.getUser())
	));
$req->run();

?>