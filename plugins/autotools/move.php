<?php

if( !chdir( dirname( __FILE__) ) )
	exit;

if( count( $argv ) > 1 )
{
	$usr = ($argv[1]=='--daemon') ? 3 : 2;
	if( count( $argv ) > $usr )
		$_SERVER['REMOTE_USER'] = $argv[$usr];
}

require_once( "./util_rt.php" );
require_once( "./autotools.php" );
eval( getPluginConf( 'autotools' ) );

// If we are not in background, run this script in background
array_shift( $argv );
if( !rtIsDaemon( $argv ) )
{
	rtDaemon( getPHP(), basename( __FILE__ ), $argv );
	// script was exited at the line above
}

// arguments array was shifted and  "--daemon" param was added, so:
// 0: --daemon
// 1: hash
// 2: username

$AutoMove_Sem = rtSemGet( fileinode( __FILE__ ) );
rtSemLock( $AutoMove_Sem );

function Debug( $str )
{
	global $autodebug_enabled;
	if( $autodebug_enabled ) rtDbg( "AutoMove", $str );
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
if( $is_ok )
{
	$at = rAutoTools::load();
	Debug( "enabled          : ".$at->enable_label );
	if( $at->enable_move )
	{
		$path_to_finished = trim( $at->path_to_finished );
		Debug( "path_to_finished : ".$path_to_finished );
		if( $path_to_finished != '' )
			$path_to_finished = rtAddTailSlash( $path_to_finished );
		else
			$is_ok = false;
	}
	else $is_ok = false;
}

// Ask info from rTorrent (torrent is assumed to be open)
if( $is_ok )
{
	$req = new rXMLRPCRequest( array (
		new rXMLRPCCommand( "d.get_base_path", $hash ),
		new rXMLRPCCommand( "d.get_name",      $hash ),
	) );

	if( $req->run() && !$req->fault )
	{
		$directory    = rTorrentSettings::get()->directory;
		$base_path    = trim( $req->val[0] );
		$torrent_name = trim( $req->val[1] );
		Debug( "get_directory    : ".$directory );
		Debug( "d.get_base_path  : ".$base_path );
		Debug( "d.get_name       : ".$torrent_name );
		if( $directory != '' && $base_path != '' )
		{
			$directory = rtAddTailSlash( $directory );
			// Make $base_path a really BASE path for downloading data
			// (not including single file or subdir for multiple files).
			// Add trailing slash, if none.
			$base_path = rtRemoveTailSlash( $base_path );
			$base_path = rtRemoveLastToken( $base_path, '/' );	// filename or dirname
			$base_path = rtAddTailSlash( $base_path );
			Debug( "base_path        : ".$base_path );
		}
		else {
			Debug( "base paths are empty!" );
			$is_ok = false;
		}
	}
	else {
		Debug( "rXMLRPCRequest() fail (get_directory, d.get_base_path)" );
		$is_ok = false;
	}
}

// Calc the destination directory
if( $is_ok )
{
	// Calc relative dir (and check if $base_path is a subdir of $directory)
	$rel_path = rtGetRelativePath( $directory, $base_path );
	Debug( "relative path    : ".$rel_path );
	if( $rel_path != '' )
	{
		if( $rel_path == './' ) $rel_path = '';
		// $dest_path is a new BASE path
		$dest_path = rtAddTailSlash( $path_to_finished.$rel_path );
		Debug( "dest_path        : ".$dest_path );
	}
	else {
		Debug( "Source files are not in subdir of \"directory\"" );
		$is_ok = false;
	}
}

// Change base_path and move files
if( $is_ok )
{
	if( !rtSetDataDir( $hash, $dest_path, true, $autodebug_enabled ) )
	{
		Debug( "rtSetDataDir() fail" );
		$is_ok = false;
	}
}

// Check .mailto file
if( $is_ok )
{
	$path = rtRemoveTailSlash( $dest_path );
	$path_to_finished = rtRemoveTailSlash( $path_to_finished );
	$mailto_file = "";
	while( $path != '' && $path != $path_to_finished )
	{
		$mailto_file = $path."/.mailto";
		if( is_file( $mailto_file ) )
		{
			Debug( "\".mailto\" file   : ".$mailto_file );
			$lines = file( $mailto_file );
			while( count( $lines ) > 0 )
			{
				$params = explode( ":", $lines[0] );
				if( count( $params ) < 2 )
					break;
				if( trim( $params[0] ) == "TO" ) $mail_to = trim( $params[1] );
				else if( trim( $params[0] ) == "FROM"    ) $mail_from = trim( $params[1] );
				else if( trim( $params[0] ) == "SUBJECT" ) $subject   = trim( $params[1] );
				else break;
				array_shift( $lines );
			}
			if( $mail_to == '' )
			{
				Debug( "mail recepient is not set!" );
			}
			else {
				Debug( "mail to          : ".$mail_to   );
				Debug( "mail from        : ".$mail_from );
				Debug( "mail subject     : ".$subject   );
				$subject = str_replace( "{TORRENT}", $torrent_name, $subject );
				$message = implode( '', $lines );
				$message = str_replace( "{TORRENT}", $torrent_name, $message );
				$headers  = "From: ".$mail_from."\r\n";
				$headers .= "Content-type: text/plain; charset=utf-8"."\r\n";
				if( !mail( $mail_to, $subject, $message, $headers ) )
				{
					Debug( "mail() to \"".$mail_to."\" fail!" );
					$is_ok = false;
				}
			}
			break;
		}
		$path = rtRemoveLastToken( $path, "/" );
		$mailto_file = "";
	}
	if( $mailto_file == '' )
		Debug( "\".mailto\" file   : not found!" );
}

Debug( "--- end ---" );

rtSemUnlock( $AutoMove_Sem );

?>