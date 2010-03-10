<?php

if($theSettings->iVersion<0x804)
{
	$cmd = new rXMLRPCCommand('on_insert');
	$cmd1 = new rXMLRPCCommand('on_insert');
}
else
{
       	$cmd = new rXMLRPCCommand('system.method.set_key','event.download.inserted_new');
       	$cmd1 = new rXMLRPCCommand('system.method.set_key','event.download.inserted_new');
}
$cmd->addParameters( array('add_trackers1'.getUser(), 'cat=') );
$cmd1->addParameters( array('add_trackers2'.getUser(), 'cat=') );
$req = new rXMLRPCRequest(array($cmd,$cmd1));
$req->run();

?>
