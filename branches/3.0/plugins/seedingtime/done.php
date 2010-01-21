<?php

$req = new rXMLRPCRequest( new rXMLRPCCommand( "system.method.set_key", 
		array("event.download.finished","seedingtime",'cat=') ) );
$req->run();

?>
