<?php

$rootPath = "./";
if( !is_file( "util.php" ) ) $rootPath = "../../";
require_once( $rootPath."util.php" );
require_once( $rootPath."xmlrpc.php" );
require_once( "at_utils.php" );
require_once( "autotools.php" );
require_once( "conf.php" );

Debug( "" );
Debug( "--- label.php begin ---" );

if( count( $argv ) < 1 )
{
	Debug( "Called without arguments (hash wanted)" );
	exit;
}
$hash = $argv[1];

// Read configuration
$at = rAutoTools::load();
Debug( "AutoLabel state : ".$at->enable_label );
if( !$at->enable_label )
	exit;

$req = new rXMLRPCRequest( array (
	new rXMLRPCCommand( "get_directory" ),
	new rXMLRPCCommand( "d.get_directory", $hash ),
	new rXMLRPCCommand( "d.get_custom3",   $hash ),
	new rXMLRPCCommand( "d.is_multi_file", $hash ),
) );
if( $req->run() && !$req->fault )
{
	//$i = 0;
	//foreach( $req->strings as $str ) Debug( $i++.' '.$str );
	//$i = 0;
	//foreach( $req->i8s as $str ) Debug( $i++.' '.$str );

	$is_multy_file = ( $req->i8s[0] != 0 );
	$default_dir = trim( $req->strings[0] );
	$torrent_dir = trim( $req->strings[1] );
	$custom3     = trim( $req->strings[2] );
	Debug( "get_directory   : ".$default_dir );
	Debug( "d.get_directory : ".$torrent_dir );
	Debug( "d.get_custom3   : ".$custom3 );
	Debug( "d.is_multy_file : ".$is_multy_file );
	// "$custom3 == 1" -> torrent is NOT NEW (modified by another plugin)
	if( $default_dir == '' || $torrent_dir == '' || $custom3 == '1' )
		exit;

	if( $is_multy_file )
		$torrent_dir = RemoveLastToken( $torrent_dir, '/' );
	$label = GetRelativePath( $default_dir, $torrent_dir );
	Debug( "Label           : ".$label );
	if( $label == '' || $label == './' )
		exit;

	$cmd = new rXMLRPCCommand( "d.set_custom1", array (
		$hash,
		rawurlencode( $label )
	) );
	$req = new rXMLRPCRequest( $cmd );
	$req->run();
}
else {
	Debug( "rXMLRPCRequest() fail!" );
}

Debug( "--- label.php end ---" );

?>
