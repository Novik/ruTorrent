<?php

$req = new rXMLRPCRequest(array(
	$theSettings->getOnInsertCommand(array('_exratio1'.getUser(), getCmd('cat='))),
	$theSettings->getOnInsertCommand(array('_exratio2'.getUser(), getCmd('cat=')))
	));
$req->run();

?>