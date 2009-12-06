<?php

require_once( '../../xmlrpc.php' );
require_once( '../../util.php' );
require_once( 'util_rt.php' );
require_once( 'conf.php' );

function Debug( $str )
{
        global $datadir_debug_enabled;
        if( $datadir_debug_enabled ) rtDbg( "DataDir: ".$str );
}

ignore_user_abort( true );
set_time_limit( 0 );

umask( $datadir_umask );
$errors = array();

if( !isset( $HTTP_RAW_POST_DATA ) )
	$HTTP_RAW_POST_DATA = file_get_contents( "php://input" );
if( isset( $HTTP_RAW_POST_DATA ) )
{
	$vars = split( '&', $HTTP_RAW_POST_DATA );
	$hash = null;
	$datadir = '';
	$move_datafiles = '0';
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
		else if( $parts[0] == "move_datafiles" )
		{
			$move_datafiles = $parts[1];
		}
	}

	Debug( "" );
	Debug( "--- begin ---" );
	if( $move_datafiles == '1' )
		Debug( $datadir.", move files" );
	else
		Debug( $datadir.", don't move files" );
	Debug( "run mode: \"".$datadir_runmode."\"" );

//	rtExec( "execute",
//		array( "/tmp/test.sh", __FILE__, ), true );

	if( $hash && $datadir != '' && $datadir_runmode == 'rtorrent' )
	{
		$script_dir = rtRemoveLastToken( __FILE__, '/' );	// filename or dirname
		$script_dir = rtAddTailSlash( $script_dir );
		$res = rtExec( "execute",
			array(
				$script_dir."setdir.sh",
				$pathToPHP,
				$hash,
				$datadir,
				$move_datafiles,
			),
			true );
		if( !$res )
		{
			$errors[] = array('desc'=>"WUILang.datadirSetDirFail", 'prm'=>$datadir);
		}
	}

	if( $hash && $datadir != '' && $datadir_runmode == 'webserver' )
	{
		if( !is_dir( $datadir ) )
		{
			// recursive mkdir() only after PHP_5.0
			mkdir( $datadir, 0777, true );
			//system( 'mkdir -p "'.$datadir.'"' );
		}
		if( !is_dir( $datadir ) )
		{
			Debug( "no such directory: \"".$datadir."\"" );
			$errors[] = array('desc'=>"WUILang.datadirDirNotFound", 'prm'=>$datadir);
		}
		elseif( !rtSetDataDir( $hash, $datadir, $move_datafiles == '1', $datadir_debug_enabled ) )
		{
			Debug( "rtSetDataDir() fail!" );
			$errors[] = array('desc'=>"WUILang.datadirSetDirFail", 'prm'=>$datadir);
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

Debug( "--- end ---" );

?>