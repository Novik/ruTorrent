<?php

require_once( 'util.php' );

$debug_erasedata = false;

if( $theSettings->iVersion < 0x804 )
	$s = 'on_erase</methodName><params>';
else
	$s = 'system.method.set_key</methodName><params><param><value><string>event.download.erased</string></value></param>';

if( $debug_erasedata ) toLog("--- erasedata begin ---");


//
// commands are always executed in the alphabetical order of their tag names
//

// call d.open to refresh d.get_base_path= variable
$content =
	'<?xml version="1.0" encoding="UTF-8"?>'.
	'<methodCall><methodName>'.$s.
	'<param><value><string>erasedata_00</string></value></param>'.
	'<param><value><string>branch=d.get_custom5=,d.open=</string></value></param>'.
	'</params>'.
	'</methodCall>';
$result = send2RPC( $content );
if( $debug_erasedata )
{
	toLog( "content : ".$content );
	toLog( "result  : ".$result );
}

// remove all data files, included into torrent
$content =
	'<?xml version="1.0" encoding="UTF-8"?>'.
	'<methodCall><methodName>'.$s.
	'<param><value><string>erasedata_01</string></value></param>'.
	'<param><value><string>branch=d.get_custom5=,"f.multicall=default,\"execute={rm,-rf,--,$f.get_frozen_path=}\""</string></value></param>'.
	'</params>'.
	'</methodCall>';
$result = send2RPC( $content );
if( $debug_erasedata )
{
	toLog( "content : ".$content );
	toLog( "result  : ".$result );
}


// remove all empty directories
// security issue: call script with path to cleanup as parameter,
//                 but it's not critical - we delete empty dirs only
$content =
	'<?xml version="1.0" encoding="UTF-8"?>'.
	'<methodCall><methodName>'.$s.
	'<param><value><string>erasedata_02</string></value></param>'.
	// "$d.get_base_path" can be a single data file or data subdir
	'<param><value><string>branch=d.get_custom5=,"execute={'.$theSettings->path.'plugins/erasedata/cleanup.sh,$d.get_base_path=}"</string></value></param>'.
	'</params>'.
	'</methodCall>';
$result = send2RPC( $content );
if( $debug_erasedata )
{
	toLog( "content : ".$content );
	toLog( "result  : ".$result );
}

$theSettings->registerPlugin( "erasedata" );

if( $debug_erasedata ) toLog("--- erasedata end ---");

?>