<?php

$req = new rXMLRPCRequest( $theSettings->getOnFinishedCommand(array("unpack".getUser(),getCmd('cat='))) );
$req->run();

?>