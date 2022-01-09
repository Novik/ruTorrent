<?php

if( !chdir( dirname( __FILE__) ) )
	exit();

if( count( $argv ) > 7 )
	$_SERVER['REMOTE_USER'] = $argv[7];

require_once( "./util_rt.php" );
require_once( "./autotools.php" );
eval( FileUtil::getPluginConf( 'autotools' ) );

//------------------------------------------------------------------------------
function Debug( $str )
{
	global $autodebug_enabled;
	if( $autodebug_enabled ) rtDbg( "AutoMove", $str );
}


//------------------------------------------------------------------------------
function skip_move($files) 
{
	global $at;
	$filter = $at->skip_move_for_files;
	if(strlen($filter)>0)
    	{
		Debug("using filter:".$filter);
		foreach($files as $file) 
		{
			if ( preg_match($filter.'u',$file)==1) 
			{
				return true;
			}
	    	}
		Debug("filter: " . $filter . " did not match any files in" . implode(" | ",$files) .". end");
	}
	return(false);
}

//------------------------------------------------------------------------------
function operationOnTorrentFiles($torrent,&$base_path,$base_file,$is_multy_file,$dest_path,$fileop_type)
{
	global $autodebug_enabled;

	$ret = false;
	if( $is_multy_file )
		$sub_dir = rtAddTailSlash( $base_file );	// $base_file - is a directory
	else
		$sub_dir = '';					// $base_file - is really a file

	$base_path.=$sub_dir;
	$dest_path.=$sub_dir;

	Debug( "Operation ".$fileop_type );
	Debug( "from ".$base_path );
	Debug( "to   ".$dest_path );

        $files = array();
        $info = $torrent->info;
	if(isset($info['files']))
		foreach($info['files'] as $key=>$file)
			$files[] = implode('/',$file['path']);
	else
		$files[] = $info['name'];

    if (skip_move($files)){
        $ret = false;
        return ($ret);
    }

	if( $base_path != $dest_path && is_dir( $base_path ) )
	{
		if( rtOpFiles( $files, $base_path, $dest_path, $fileop_type, $autodebug_enabled ) )
		{
			if(($fileop_type=="Move") && ( $sub_dir != '' ))
			{
				Debug( "clean ".$base_path );
				rtRemoveDirectory( $base_path, false );
			}
			$ret = true;
		}
	}
	$base_path = $dest_path;
	return($ret);
}

//------------------------------------------------------------------------------
Debug( "" );
Debug( "--- begin ---" );

$hash      = $argv[1];
$base_path = $argv[2];
$base_name = $argv[3];
$is_multi  = $argv[4];
$label	   = rawurldecode($argv[5]);
$name	   = $argv[6];
$at = rAutoTools::load();

if( $at->enable_move && (@preg_match($at->automove_filter.'u',$label)==1) )
{
	$path_to_finished = trim( $at->path_to_finished );
	$fileop_type = $at->fileop_type;
	$session  = rTorrentSettings::get()->session;
	if( ($path_to_finished != '') && !empty($session) )
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
				$rel_path  = rtGetRelativePath( $directory, $base_path );
				//------------------------------------------------------------------------------
				// !! this is a feature !!
				// ($rel_path == '') means, that $base_path is NOT a SUBDIR of $directory at all
				// so, we have to skip all automove actions
				// for example, if we don't want torrent to be automoved - we save it out of $directory subtree
				//------------------------------------------------------------------------------
				if( $rel_path != '' )
				{
					if( $rel_path == './' ) $rel_path = '';
					$dest_path = rtAddTailSlash( $path_to_finished.$rel_path );
					// last condition avoids appending duplicate path from combining folder and label (eg autowatch and autolabel)
					if($at->addLabel && ($label!='') && ($label!=trim($rel_path,'/')))
		        			$dest_path.=FileUtil::addslash($label);
			        	if($at->addName && ($name!=''))
						$dest_path.=FileUtil::addslash($name);					
					if(operationOnTorrentFiles($torrent,$base_path,$base_name,$is_multi,$dest_path,$fileop_type))
					{
//						if($fileop_type=="Move")
							echo $base_path;
						$path = rtRemoveTailSlash( $dest_path );
						$path_to_finished = rtRemoveTailSlash( $path_to_finished );
						$mailto_file = "";
						while( $path != '' && $path != $path_to_finished )
						{
							$mailto_file = $path."/.mailto";
							if( is_file( $mailto_file ) )
							{
								Debug( "\".mailto\" file   : ".$mailto_file );
								$mail_to   = "";
								$mail_cc   = ""; 
								$mail_bcc  = "";
								$mail_from = "";
								$subject   = "";
								$lines = file( $mailto_file );
								while( count( $lines ) > 0 )
								{
									$params = explode( ":", $lines[0] );
									if( count( $params ) < 2 )
										break;
									if     ( trim( $params[0] ) == "TO"      ) $mail_to   = trim( $params[1] );
									else if( trim( $params[0] ) == "CC"      ) $mail_cc   = trim( $params[1] );
									else if( trim( $params[0] ) == "BCC"     ) $mail_bcc  = trim( $params[1] );
									else if( trim( $params[0] ) == "FROM"    ) $mail_from = trim( $params[1] );
									else if( trim( $params[0] ) == "SUBJECT" ) $subject   = trim( $params[1] );
									else break;
									array_shift( $lines );
								}
								if( $mail_to == "" )
									Debug( "mail recepient is not set!" );
								else 
								{
									Debug( "mail to          : ".$mail_to   );
									Debug( "mail cc          : ".$mail_cc   );
									Debug( "mail bcc         : ".$mail_bcc  );
									Debug( "mail from        : ".$mail_from );
									Debug( "mail subject     : ".$subject   );
									$torrent_name = $torrent->name();
									$subject = str_replace( "{TORRENT}", $torrent_name, $subject );
									$message = implode( '', $lines );
									$message = str_replace( "{TORRENT}", $torrent_name, $message );
									$headers  = "From: ".$mail_from."\r\n";
									if( $mail_cc != "" )
										$headers .= "CC: ".$mail_cc."\r\n";
									if( $mail_bcc != "" )
										$headers .= "BCC: ".$mail_bcc."\r\n";
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

Debug( "--- end ---" );
