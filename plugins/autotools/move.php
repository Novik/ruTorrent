<?php

if( !chdir( dirname( __FILE__) ) )
	exit;

if( count( $argv ) > 6 )
	$_SERVER['REMOTE_USER'] = $argv[6];

require_once( "./util_rt.php" );
require_once( "./autotools.php" );
eval( getPluginConf( 'autotools' ) );

function Debug( $str )
{
	global $autodebug_enabled;
	if( $autodebug_enabled ) rtDbg( "AutoMove", $str );
}

function moveTorrentFiles($torrent,$base_path,$base_file,$is_multy_file,$dest_path)
{
	global $autodebug_enabled;
	$ret = false;
	if( $is_multy_file )
		$sub_dir = rtAddTailSlash( $base_file );	// $base_file - is a directory
	else
		$sub_dir = '';					// $base_file - is really a file
	Debug( "from ".$base_path.$sub_dir );
	Debug( "to   ".$dest_path.$sub_dir );

        $files = array();
        $info = $torrent->info;
	if(isset($info['files']))
		foreach($info['files'] as $key=>$file)
			$files[] = implode('/',$file['path']);
	else
		$files[] = $info['name'];

	if( $base_path.$sub_dir != $dest_path.$sub_dir && is_dir( $base_path.$sub_dir ) )
	{
		if( rtMoveFiles( $files, $base_path.$sub_dir, $dest_path.$sub_dir, $autodebug_enabled ) )
		{
			// Recursively remove source dirs without files
			Debug( "clean ".$base_path.$sub_dir );
			if( $sub_dir != '' )
				rtRemoveDirectory( $base_path.$sub_dir, false );
			$ret = true;
		}
	}
	return($ret);
}

$session = $argv[1];
$hash = $argv[2];
$base_path = $argv[3];
$base_name = $argv[4];
$is_multi = $argv[5];
$at = rAutoTools::load();
if( $at->enable_move && !empty($session))
{
	$path_to_finished = trim( $at->path_to_finished );
	if( $path_to_finished != '' )
	{
		$path_to_finished = rtAddTailSlash( $path_to_finished );
		$fname = rtAddTailSlash($session).$hash.".torrent";
		$directory    = rTorrentSettings::get()->directory;
		if(is_readable($fname) && !empty($directory))
		{
			$torrent = new Torrent( $fname );		
			if( !$torrent->errors() )
			{
				$directory = rtAddTailSlash( $directory );
				$base_path = rtRemoveTailSlash( $base_path );
				$base_path = rtRemoveLastToken( $base_path, '/' );	// filename or dirname
				$base_path = rtAddTailSlash( $base_path );
				$rel_path = rtGetRelativePath( $directory, $base_path );
				if( $rel_path != '' )
				{
					if( $rel_path == './' ) $rel_path = '';
					$dest_path = rtAddTailSlash( $path_to_finished.$rel_path );
					if(moveTorrentFiles($torrent,$base_path,$base_name,$is_multi,$dest_path))
					{
						echo $dest_path;
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
									Debug( "mail recepient is not set!" );
								else 
								{
									Debug( "mail to          : ".$mail_to   );
									Debug( "mail from        : ".$mail_from );
									Debug( "mail subject     : ".$subject   );
									$torrent_name = $torrent->name();
									$subject = str_replace( "{TORRENT}", $torrent_name, $subject );
									$message = implode( '', $lines );
									$message = str_replace( "{TORRENT}", $torrent_name, $message );
									$headers  = "From: ".$mail_from."\r\n";
									$headers .= "Content-type: text/plain; charset=utf-8"."\r\n";
									if( !mail( $mail_to, $subject, $message, $headers ) )
										Debug( "mail() to \"".$mail_to."\" fail!" );
								}
								break;
							}
							$path = rtRemoveLastToken( $path, "/" );
							$mailto_file = "";
						}
						if( $mailto_file == '' )
							Debug( "\".mailto\" file   : not found!" );
					}
				}
			}
		}
	}
}

?>