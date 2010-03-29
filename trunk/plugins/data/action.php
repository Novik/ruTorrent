<?php
require_once( '../../php/xmlrpc.php' );

if(isset($_REQUEST['result']))
{
	$content = 'log(theUILang.cantAccessData);';
	if(!ini_get("zlib.output_compression"))
		header("Content-Length: ".strlen($content));
	header("Content-Type: text/html");
	exit($content);
}

if(isset($_REQUEST['hash']) && isset($_REQUEST['no']))
{
	$req = new rXMLRPCRequest( 
		new rXMLRPCCommand( "f.get_frozen_path", array($_REQUEST['hash'],intval($_REQUEST['no']))) );
	if($req->success())
	{
		$filename = $req->val[0];
		if($filename=='')
		{
			$req = new rXMLRPCRequest( array(
				new rXMLRPCCommand( "d.open", $_REQUEST['hash'] ),
				new rXMLRPCCommand( "f.get_frozen_path", array($_REQUEST['hash'],intval($_REQUEST['no'])) ),
				new rXMLRPCCommand( "d.close", $_REQUEST['hash'] ) ) );
			if($req->success())
				$filename = $req->val[1];
		}
		if(is_file($filename) && is_readable($filename))
		{
			$stat = @stat($filename);
			$etag = sprintf('%x-%x-%x', $stat['ino'], $stat['size'], $stat['mtime'] * 1000000);
			header('Expires: ');
			header('Cache-Control: ');
			header('Pragma: ');
			header('Etag: "' . $etag . '"');
			header('Last-Modified: ' . date('r', $stat['mtime']));
			if( 	(isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag) ||
                        	(isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $stat['mtime']))
				header('HTTP/1.0 304 Not Modified');
			else
			{
				set_time_limit(0);
				header('Last-Modified: ' . date('r', $stat['mtime']));
				header('Etag: "' . $etag . '"');
				header('Accept-Ranges: bytes');
				if(!ini_get("zlib.output_compression"))
					header('Content-Length:' . $stat['size']);
				header('Content-Type: application/octet-stream');
				$fname = end(explode('/',$filename));
				if(isset($_SERVER['HTTP_USER_AGENT']) && strstr($_SERVER['HTTP_USER_AGENT'],'MSIE'))
					$fname = rawurlencode($fname);
				header('Content-Disposition: attachment; filename="'.$fname.'"');
				header('Content-Transfer-Encoding: binary');
				header('Content-Description: File Transfer');
				header('HTTP/1.0 200 OK');
				@readfile($filename);
				exit;
			}
		}
	}
}
header("Location: ".$_SERVER['PHP_SELF'].'?result=0');
?>
