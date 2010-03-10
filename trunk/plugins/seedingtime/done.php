<?php

$req = new rXMLRPCRequest( array(
                new rXMLRPCCommand( "system.method.set_key", 
			array("event.download.finished","seedingtime".getUser(),'cat=') ),
                new rXMLRPCCommand( "system.method.set_key", 
			array("event.download.inserted_new","addtime".getUser(),'cat=') )
		));
$req->run();

?>
