<?php

if( !chdir( dirname( __FILE__) ) )
	exit();

if( count( $argv ) > 1 )
{
	$usr = ($argv[1]=='--daemon') ? 3 : 2;
	if( count( $argv ) > $usr )
		$_SERVER['REMOTE_USER'] = $argv[$usr];
}

require_once( "./util_rt.php" );
require_once( "./autotools.php" );
eval( FileUtil::getPluginConf( 'autotools' ) );

// If we are not in background, run this script in background
array_shift( $argv );
if( !rtIsDaemon( $argv ) )
{
	rtDaemon( Utility::getPHP(), basename( __FILE__ ), $argv );
	// script was exited at the line above
}

// arguments array was shifted and  "--daemon" param was added, so:
// 0: --daemon
// 1: hash
// 2: username

$AutoLabel_Sem = rtSemGet( fileinode( __FILE__ ) );
rtSemLock( $AutoLabel_Sem );

function Debug( $str )
{
	global $autodebug_enabled;
	if( $autodebug_enabled ) rtDbg( "AutoLabel", $str );
}

Debug( "" );
Debug( "--- begin ---" );

$is_ok = true;
if( count( $argv ) < 2 )
{
	Debug( "called without arguments (hash wanted)" );
	$is_ok = false;
}
else $hash = $argv[1];

// Read configuration
$label = "";
if( $is_ok )
{
	$at = rAutoTools::load();
	Debug( "enabled         : ".$at->enable_label );
	if( !$at->enable_label )
		$is_ok = false;
	Debug( "template        : \"".$at->label_template."\"" );
	$label = $at->label_template;
	if( strlen( trim( $label ) ) == 0 ) $label = "{DIR}";
}

// Get info from rTorrent
if( $is_ok && strpos( $label, "{DIR}" ) !== false )
{
	$req = new rXMLRPCRequest( array (
		new rXMLRPCCommand( "d.get_directory", $hash ),
		new rXMLRPCCommand( "d.get_custom3",   $hash ),
		new rXMLRPCCommand( "d.is_multi_file", $hash ),
	) );
	if( $req->run() && !$req->fault )
	{
		$is_multy_file = ( $req->val[2] != 0 );
		$default_dir = rTorrentSettings::get()->directory;
		$torrent_dir = trim( $req->val[0] );
		$custom3     = trim( $req->val[1] );
		Debug( "get_directory   : ".$default_dir );
		Debug( "d.get_directory : ".$torrent_dir );
		Debug( "d.get_custom3   : ".$custom3 );
		Debug( "d.is_multy_file : ".$is_multy_file );
		if( $default_dir == '' || $torrent_dir == '' )
		{
			Debug( "base paths are not set" );
			$is_ok = false;
		}
		elseif( $custom3 == '1' )
		{
			Debug( "torrent is NOT NEW (modified by another plugin)" );
			$is_ok = false;
		}
		else {
			if( $is_multy_file )
				$torrent_dir = rtRemoveLastToken( $torrent_dir, '/' );
			$lbl_dir = rtGetRelativePath( $default_dir, $torrent_dir );
			if( $lbl_dir == "./" ) $lbl_dir = "";
			$label = str_replace( "{DIR}", $lbl_dir, $label );
		}
	}
	else {
		Debug( "rXMLRPCRequest() fail" );
		$is_ok = false;
	}
}

// Get info about tracker
if( $is_ok && strpos( $label, "{TRACKER}" ) !== false )
{
	$req = new rXMLRPCRequest( array(
		new rXMLRPCCommand( "t.multicall",
			array( $hash, "", getCmd("t.is_enabled="), getCmd("t.get_type="), getCmd("t.get_group="), getCmd("t.get_url=") )
		)
	));
	$req->setParseByTypes();
	if( $req->run() && !$req->fault )
	{
		for( $i = 0; $i < count( $req->strings ); $i++ )
		{
			// enabled, type == 1, group == 0
			if( $req->i8s[$i*3] == 0 || $req->i8s[$i*3+1] != 1 || $req->i8s[$i*3+2] != 0 )
				continue;
			$lbl_tracker = parse_url( $req->strings[$i], PHP_URL_HOST );
			// if tracker is not an IP address, then
			if( preg_match( "/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/", $lbl_tracker ) != 1 )
			{
				// get 2-nd level domain only
				$pos = strpos( $lbl_tracker, '.' );
				if( $pos !== false )
				{
					$tmp = substr( $lbl_tracker, $pos + 1 );
					if( strpos( $tmp, '.' ) !== false )
						$lbl_tracker = $tmp;
				}
			}
			Debug( "tracker         : ".$lbl_tracker );
			$label = str_replace( "{TRACKER}", $lbl_tracker, $label );
			break; // we need the first tracker only
		}
	}
	else {
		Debug( "rXMLRPCRequest() fail (t.multicall)" );
		$is_ok = false;
	}
}

// Get info about tracker
if( $is_ok &&
	preg_match( "/{NOW:?([^}]*)}/", $label, $match ) > 0 &&
	count( $match ) > 0 )
{
	// $matches[0] will contain the text that matched the full pattern, 
	// $matches[1] will have the text that matched the first captured parenthesized subpattern, and so on

	Debug( "count           : \"".count( $match )."\"" );
	Debug( "match[1]        : \"".$match[1]."\"" );
	if( count( $match ) > 1 && strlen( $match[1] ) > 0 )
		$lbl_now = strftime( $match[1] );
	else
		$lbl_now = strftime( '%Y-%m-%d' );
	$label = str_replace( $match[0], $lbl_now, $label );
}

// Set a label
if( $is_ok )
{
	Debug( "label           : \"".$label."\"" );
	if( ($label != "") && rtExec( "d.set_custom1", array( $hash, rawurlencode( $label ) ), $autodebug_enabled ))
		rTorrentSettings::get()->pushEvent( "LabelChanged", array( "hash"=>$hash, "label"=>$label ) );
}

Debug( "--- end ---" );

rtSemUnlock( $AutoLabel_Sem );
