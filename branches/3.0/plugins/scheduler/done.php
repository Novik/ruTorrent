<?php

$req = new rXMLRPCRequest( new rXMLRPCCommand('schedule_remove', 'scheduler') );
$req->run();

?>
