<?php

if( $theSettings->iVersion < 0x804 )
{
	$cmdInsert = new rXMLRPCCommand('on_insert');
	$cmdFinished = new rXMLRPCCommand('on_finished');
}
else
{
	$cmdInsert = new rXMLRPCCommand('system.method.set_key','event.download.inserted_new');
	$cmdFinished = new rXMLRPCCommand('system.method.set_key','event.download.finished');
}
$cmdInsert->addParameters( array('autolabel', 'cat=') );
$cmdFinished->addParameters( array('automove', 'cat=') );
$cmdSchedule = new rXMLRPCCommand('schedule_remove', 'autowatch');
$req = new rXMLRPCRequest( array( $cmdInsert, $cmdFinished, $cmdSchedule ) );
$req->run();

?>