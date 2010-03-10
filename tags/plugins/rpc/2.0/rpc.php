<?php

require_once( '../../util.php' );

if (!isset($HTTP_RAW_POST_DATA))
   $HTTP_RAW_POST_DATA = file_get_contents("php://input");
$result = send2RPC($HTTP_RAW_POST_DATA);
if(strlen($result)>0)
{
	header("Content-Type: text/xml; charset=UTF-8");
	$pos = strpos($result, "\r\n\r\n");
	if($pos !== false)
		$result = substr($result,$pos+4);
}
else
{
	header("HTTP/1.0 500 Server Error");
	$result = "Link to XMLRPC failed. May be, rTorrent is down?";
	header("Content-Type: text/html; charset=UTF-8");
}
header("Content-Length: ".strlen($result));
header("Cache-Control: no-cache");
echo $result;

?>