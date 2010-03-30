<?php

require_once( '../../php/xmlrpc.php' );

if (!isset($HTTP_RAW_POST_DATA))
	$HTTP_RAW_POST_DATA = file_get_contents("php://input");
$result = rXMLRPCRequest::send($HTTP_RAW_POST_DATA);
if($result)
{
	header("Content-Type: text/xml; charset=UTF-8");
	$pos = strpos($result, "\r\n\r\n");
	if($pos !== false)
		$result = substr($result,$pos+4);
}
else
{
	header("HTTP/1.0 500 Server Error");
	header("Content-Type: text/html; charset=UTF-8");
	$result = "Link to XMLRPC failed. May be, rTorrent is down?";
}
cachedEcho($result);

?>