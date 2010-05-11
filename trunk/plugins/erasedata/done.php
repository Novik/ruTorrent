<?php

$params = array( getCmd('cat='), getCmd('cat=') );
if(isLocalMode())
	$params[] = getCmd('cat=');
$req = new rXMLRPCRequest();
foreach( $params as $i=>$prm )
	$req->addCommand($theSettings->getOnEraseCommand(array('erasedata'.$i.getUser(), $prm )));
$req->run();

?>