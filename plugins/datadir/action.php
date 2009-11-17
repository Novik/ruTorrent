<?php

require_once( '../../xmlrpc.php' );

ignore_user_abort( true );
set_time_limit( 0 );

$errors = array();

if( !isset( $HTTP_RAW_POST_DATA ) )
	$HTTP_RAW_POST_DATA = file_get_contents( "php://input" );
if( isset( $HTTP_RAW_POST_DATA ) )
{
	$vars = split( '&', $HTTP_RAW_POST_DATA );
	$hash = null;
	$datadir = '';
	foreach( $vars as $var )
	{
		$parts = split( "=", $var );
		if( $parts[0] == "hash" )
		{
			$hash = $parts[1];
		}
		else if( $parts[0] == "datadir" )
		{
			$datadir = trim( rawurldecode( $parts[1] ) );
		}
	}
	if( $hash )
	{
		if( is_dir( realpath( $datadir ) ) )
		{
			//toLog( "d.set_directory=".$datadir );
			$req = new rXMLRPCRequest( array(
				new rXMLRPCCommand( "d.stop",  $hash ),
				//new rXMLRPCCommand( "d.close", $hash ),
				new rXMLRPCCommand( "d.set_directory", array( $hash, $datadir ) ),
				//new rXMLRPCCommand( "d.open",  $hash ),
				new rXMLRPCCommand( "d.start", $hash ),
			) );
			if( !$req->run() || $req->fault )
			{
				toLog( "rXMLRPCRequest() fail (d.set_directory && d.start)!" );
				$errors[] = array('desc'=>"WUILang.datadirSetDirFail", 'prm'=>$datadir);
			}
		}
		else {
			toLog( "No such directory: \"".$datadir."\"" );
			$errors[] = array('desc'=>"WUILang.datadirDirNotFound", 'prm'=>$datadir);
		}
	}
}

$ret = "{ errors: [";
foreach( $errors as $err )
	$ret .= "{ prm: \"".addslashes( $err['prm'] )."\", desc: ".$err['desc']." },";
$len = strlen( $ret );
if( $ret[$len - 1] == ',' )
	$ret = substr( $ret, 0, $len - 1 );
$ret .= "]}";

$content = '<?xml version="1.0" encoding="UTF-8"?><data><![CDATA['.$ret.']]></data>';
header( "Content-Length: ".strlen( $content ) );
header( "Content-Type: text/xml; charset=UTF-8" );
echo $content;

?>
