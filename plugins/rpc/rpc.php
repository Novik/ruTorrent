<?php

require_once( '../../php/xmlrpc.php' );

if (!isset($HTTP_RAW_POST_DATA))
	$HTTP_RAW_POST_DATA = file_get_contents("php://input");

if(isset($HTTP_RAW_POST_DATA) 
	&& !preg_match("/(execute|import)\s*=/i",$HTTP_RAW_POST_DATA))
{
	$result = rXMLRPCRequest::send($HTTP_RAW_POST_DATA,false);
	if(!empty($result))
	{
		$pos = strpos($result, "\r\n\r\n");
		if($pos !== false)
			$result = substr($result,$pos+4);
		CachedEcho::send($result, "text/xml");
	}
}

header("HTTP/1.0 500 Server Error");
CachedEcho::send("Link to XMLRPC failed. May be, rTorrent is down?","text/html");
