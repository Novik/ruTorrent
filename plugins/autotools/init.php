<?php

require_once( 'util.php' );
require_once( 'plugins/autotools/autotools.php');
require_once( 'plugins/autotools/conf.php' );

if( !$pathToPHP || $pathToPHP == '' )
	$pathToPHP = 'php';

if( DO_DIAGNOSTIC )
{
	if( !$pathToPHP || $pathToPHP == "" )
		findRemoteEXE( 'php', "utWebUI.showAutoToolsError('WUILang.autotoolsPHPNotFound');", $remoteRequests );

	@chmod( $theSettings->path.'plugins/autotools/label.sh', 0755 );
	@chmod( $theSettings->path.'plugins/autotools/label.php', 0644 );
	@chmod( $theSettings->path.'plugins/autotools/move.sh', 0755 );
	@chmod( $theSettings->path.'plugins/autotools/move.php', 0644 );

	if( !isUserHavePermission( $theSettings->uid, $theSettings->gid, $theSettings->path.'plugins/autotools/label.sh', 0x0005 ) )
		$jResult .= "utWebUI.showAutoToolsError('WUILang.autotoolsLabelShNotAvailable');";
	if( !isUserHavePermission( $theSettings->uid, $theSettings->gid, $theSettings->path.'plugins/autotools/label.php',0x0004 ) )
		$jResult .= "utWebUI.showAutoToolsError('WUILang.autotoolsLabelPhpNotAvailable');";
	if( !isUserHavePermission( $theSettings->uid, $theSettings->gid, $theSettings->path.'plugins/autotools/move.sh', 0x0005 ) )
		$jResult .= "utWebUI.showAutoToolsError('WUILang.autotoolsMoveShNotAvailable');";
	if( !isUserHavePermission( $theSettings->uid, $theSettings->gid, $theSettings->path.'plugins/autotools/move.php',0x0004 ) )
		$jResult .= "utWebUI.showAutoToolsError('WUILang.autotoolsMovePhpNotAvailable');";
}


if( $theSettings->iVersion < 0x804 )
	$s = 'on_insert</methodName><params>';
else
	$s = 'system.method.set_key</methodName><params><param><value><string>event.download.inserted_new</string></value></param>';
$content =
	'<?xml version="1.0" encoding="UTF-8"?>'.
	'<methodCall><methodName>'.$s.
	'<param><value><string>autolabel</string></value></param>'.
	'<param><value><string>branch=$not=$d.get_custom1=,"execute={'.$theSettings->path.'plugins/autotools/label.sh'.','.$pathToPHP.',$d.get_hash=}"</string></value></param>'.
	'</params>'.
	'</methodCall>';
$result = send2RPC( $content );


if( $theSettings->iVersion < 0x804 )
	$s = 'on_finished</methodName><params>';
else
	$s = 'system.method.set_key</methodName><params><param><value><string>event.download.finished</string></value></param>';
$content =
	'<?xml version="1.0" encoding="UTF-8"?>'.
	'<methodCall><methodName>'.$s.
	'<param><value><string>automove</string></value></param>'.
	'<param><value><string>execute={'.$theSettings->path.'plugins/autotools/move.sh'.','.$pathToPHP.',$d.get_hash=}</string></value></param>'.
	'</params>'.
	'</methodCall>';
$result = send2RPC( $content );


$at = rAutoTools::load();
$jResult .= $at->get();

if( DO_DIAGNOSTIC )
{
	if( $at->enable_move )
	{
		$path_to_finished = trim( $at->path_to_finished );
		if( $path_to_finished == '' )
			$jResult .= "utWebUI.showAutoToolsError('WUILang.autotoolsNoPathToFinished');";
	}
}

?>
