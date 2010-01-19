<?php
require_once( '../../php/xmlrpc.php' );

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
			if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag)
			{
				header('Etag: "' . $etag . '"');
				header('HTTP/1.0 304 Not Modified');
			}
			elseif(isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $stat['mtime'])
			{
				header('Last-Modified: ' . date('r', $stat['mtime']));
				header('HTTP/1.0 304 Not Modified');
			}
			else
			{
				set_time_limit(0);
				header('Last-Modified: ' . date('r', $stat['mtime']));
				header('Etag: "' . $etag . '"');
				header('Accept-Ranges: bytes');
				header('Content-Length:' . $stat['size']);
				header('Content-Type: application/octet-stream');
				$filename = end(explode('/',$filename));
				if(isset($_SERVER['HTTP_USER_AGENT']) && strstr($_SERVER['HTTP_USER_AGENT'],'MSIE'))
					$filename = rawurlencode($filename);
				header('Content-Disposition: attachment; filename="'.$filename.'"');
				header('Content-Transfer-Encoding: binary');
				header('Content-Description: File Transfer');
				header('HTTP/1.0 200 OK');
				@readfile($filename);
				return;
			}
		}
	}
}

$content = 'log(theUILang.cantAccessData);';
header("Content-Length: ".strlen($content));
header("Content-Type: text/html");
echo $content;
?>
