<?php

$params = array( 'cat=', 'cat=' );
if(isLocalMode())
	$params[] = 'cat=';
$req = new rXMLRPCRequest();
foreach( $params as $i=>$prm )
{
	if( $theSettings->iVersion < 0x804 )
		$cmd = new rXMLRPCCommand("on_erase");
	else
		$cmd = new rXMLRPCCommand("system.method.set_key", "event.download.erased");
	$cmd->addParameters( array('erasedata'.$i.getUser(), $prm ) );
	$req->addCommand($cmd);
}	    
$req->run();

?>
