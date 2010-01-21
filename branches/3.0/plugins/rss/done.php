<?php

$req = new rXMLRPCRequest( new rXMLRPCCommand("schedule_remove", "rss") );
$req->run();

?>
