<?php

$req = new rXMLRPCRequest( new rXMLRPCCommand('schedule_remove', 'rutracker_check'.getUser())	);
$req->run();

?>