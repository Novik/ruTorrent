<?php

$req = new rXMLRPCRequest( array(
		$theSettings->getOnFinishedCommand(array("seedingtime".getUser(),'cat=')),
		$theSettings->getOnInsertCommand(array("addtime".getUser(),'cat='))
		));
$req->run();

?>
