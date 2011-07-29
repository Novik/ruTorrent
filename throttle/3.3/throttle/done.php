<?php

$req = new rXMLRPCRequest( $theSettings->getOnInsertCommand(array('_throttle'.getUser(), getCmd('cat='))) );
$req->run();

?>