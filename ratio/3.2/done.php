<?php

$req = new rXMLRPCRequest( new rXMLRPCCommand("schedule_remove", "ratio".getUser()) );
$req->run();

?>