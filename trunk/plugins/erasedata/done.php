<?php

$params = array( 'cat=', 'cat=' );
if(isLocalMode())
	$params[] = 'cat=';
$req = new rXMLRPCRequest();
foreach( $params as $i=>$prm )
	$req->addCommand($theSettings->getOnEraseCommand(array('erasedata'.$i.getUser(), $prm )));
$req->run();

?>
