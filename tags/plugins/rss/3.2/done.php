<?php

$req = new rXMLRPCRequest( new rXMLRPCCommand("schedule_remove", "rss".getUser()) );
$req->run();

?>