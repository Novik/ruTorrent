<?php

if($theSettings->iVersion<0x804)
	$cmd = new rXMLRPCCommand('on_insert');
else
       	$cmd = new rXMLRPCCommand('system.method.set_key','event.download.inserted_new');
$cmd->addParameters( array('add_trackers'.getUser(), 'cat=') );
$req = new rXMLRPCRequest($cmd);
$req->run();

?>
