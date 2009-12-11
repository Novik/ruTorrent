<?php

require_once( 'config.php' );
require_once( 'util.php' );
require_once( 'plugins/autotools/autotools.php');
require_once( 'plugins/autotools/conf.php' );

if( !$pathToPHP || $pathToPHP == '' )
	$pathToPHP = 'php';

$pathToAutoTools = $theSettings->path.'plugins/autotools';

if( DO_DIAGNOSTIC )
{
	if( !$pathToPHP || $pathToPHP == "" )
		findRemoteEXE( 'php', "utWebUI.showAutoToolsError('WUILang.autotoolsPHPNotFound');", $remoteRequests );

	@chmod( $pathToAutoTools.'/label.php', 0644 );
	@chmod( $pathToAutoTools.'/move.php',  0644 );
	@chmod( $pathToAutoTools.'/watch.php', 0644 );

	if( !isUserHavePermission( $theSettings->uid, $theSettings->gid, $pathToAutoTools.'/label.php',0x0004 ) )
		$jResult .= "utWebUI.showAutoToolsError('WUILang.autotoolsLabelPhpNotAvailable');";
	if( !isUserHavePermission( $theSettings->uid, $theSettings->gid, $pathToAutoTools.'/move.php',0x0004 ) )
		$jResult .= "utWebUI.showAutoToolsError('WUILang.autotoolsMovePhpNotAvailable');";
	if( !isUserHavePermission( $theSettings->uid, $theSettings->gid, $pathToAutoTools.'/watch.php',0x0004 ) )
		$jResult .= "utWebUI.showAutoToolsError('WUILang.autotoolsWatchPhpNotAvailable');";
}

if( $theSettings->iVersion < 0x804 )
	$s = 'on_insert</methodName><params>';
else
	$s = 'system.method.set_key</methodName><params><param><value><string>event.download.inserted_new</string></value></param>';
$content =
	'<?xml version="1.0" encoding="UTF-8"?>'.
	'<methodCall><methodName>'.$s.
	'<param><value><string>autolabel</string></value></param>'.
	'<param><value><string>branch=$not=$d.get_custom1=,"execute={'.$pathToPHP.','.$pathToAutoTools.'/label.php,$d.get_hash=}"</string></value></param>'.
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
	'<param><value><string>execute={'.$pathToPHP.','.$pathToAutoTools.'/move.php,$d.get_hash=}</string></value></param>'.
	'</params>'.
	'</methodCall>';
$result = send2RPC( $content );


$content =
	'<?xml version="1.0" encoding="UTF-8"?>'.
	'<methodCall>'.
	'<methodName>schedule</methodName>'.
	'<params>'.
	'<param><value><string>autowatch</string></value></param>'.
	'<param><value><string>'.'10'.'</string></value></param>'.
	'<param><value><string>'.$autowatch_interval.'</string></value></param>'.
	'<param><value><string>execute={sh,-c,'.$pathToPHP.' '.$pathToAutoTools.'/watch.php &amp;}</string></value></param>'.
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
	if( $at->enable_watch )
	{
		$path_to_watch = trim( $at->path_to_watch );
		if( $path_to_watch == '' )
			$jResult .= "utWebUI.showAutoToolsError('WUILang.autotoolsNoPathToWatch');";
	}
}

?>