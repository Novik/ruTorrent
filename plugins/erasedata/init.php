<?php
require_once( 'util.php' );
if($theSettings->iVersion<0x804)
	$s = 'on_erase</methodName><params>';
else
	$s = 'system.method.set_key</methodName><params><param><value><string>event.download.erased</string></value></param>';
send2RPC('<?xml version="1.0" encoding="UTF-8"?>'.
	'<methodCall><methodName>'.$s.
	'<param><value><string>rm_files</string></value></param>'.
	'<param><value><string>branch=d.get_custom5=,"f.multicall=default,\"execute={rm,-rf,--,$f.get_frozen_path=}\""</string></value></param>'.
	'</params>'.
	'</methodCall>');
$theSettings->registerPlugin("erasedata");
?>
