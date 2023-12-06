<?php
require_once( '../../php/xmlrpc.php' );

if(isset($_REQUEST['result']))
	CachedEcho::send('noty(theUILang.cantAccessData,"error");',"text/html");

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
		if(SendFile::send($filename))
			exit;
	}
}

if(isset($_REQUEST['readable']))
	CachedEcho::send("Cant retrieve such large file, sorry","text/html");
else
{
	header("HTTP/1.0 302 Moved Temporarily");
	header("Location: action.php?result=0");
}
