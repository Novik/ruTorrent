<?php

require_once( 'util.php' );

if( $theSettings->iVersion >= 0x805 )
{
	$content =
		'<?xml version="1.0" encoding="UTF-8"?>'.
		'<methodCall><methodName>system.method.set_key</methodName><params><param><value><string>event.download.finished</string></value></param>'.
		'<param><value><string>seedingtime</string></value></param>'.
		'<param><value><string>d.set_custom=seedingtime,"$execute_capture={date,+%s}"</string></value></param>'.
		'</params>'.
		'</methodCall>';
	$result = send2RPC( $content );
	$theSettings->registerPlugin("seedingtime");
	$jResult.="utWebUI.trtColumns.push({'text' : 'SeedingTime','width' : '100px','type' : TYPE_NUMBER}); ";
}
else
	$jResult .= "utWebUI.showSeedingTimeError('WUILang.seedingTimeBadVersion'); utWebUI.seedingTimeSupported = false;";
?>
