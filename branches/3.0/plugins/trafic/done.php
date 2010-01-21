<?php

$req = new rXMLRPCRequest( new rXMLRPCCommand("schedule_remove", "trafic") );
$req->run();

?>
