<?php

require_once( '../../php/xmlrpc.php' );
require_once( 'removewithdata.php' );

$hash = array();
$vs = array();
$mode = "";
if (!isset($HTTP_RAW_POST_DATA))
	$HTTP_RAW_POST_DATA = file_get_contents("php://input");
if(isset($HTTP_RAW_POST_DATA))
{
	foreach(explode('&', $HTTP_RAW_POST_DATA) as $var)
	{
		$parts = explode("=", $var);
		switch($parts[0])
		{
			case "hash": $hash[] = $parts[1]; break;
			case "v":    $vs[]   = rawurldecode($parts[1]); break;
			case "mode": $mode   = $parts[1]; break;
		}
	}
}

$result = null;
if($mode == "removewithdata" && count($hash))
{
	$forceDelete = isset($vs[0]) ? $vs[0] : "1";
	$result = erasedataRemoveWithData($hash, $forceDelete);
}

if(is_null($result))
{
	header("HTTP/1.0 500 Server Error");
	CachedEcho::send("Link to XMLRPC failed. Maybe, rTorrent is down?", "text/html");
}
else
	CachedEcho::send(JSON::safeEncode($result), "application/json");
