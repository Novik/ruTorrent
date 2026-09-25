<?php

require_once( 'Snoopy.class.inc');
require_once( 'rtorrent.php' );
set_time_limit(0);

/**
 * Encode a value for use as a literal in the script this page serves.
 *
 * The result of this page is evaluated by the client (js/content.js), so
 * every reflected value has to be a complete literal that no input can end.
 * The HEX flags also keep <, >, & and both quote characters out of the bytes,
 * so the response cannot be turned into markup by asking for it directly.
 *
 * A unix filename is a string of bytes and need not be valid UTF-8, and it
 * reaches here as name[]. json_encode() answers false for bytes it cannot
 * encode, which concatenates as nothing at all and leaves the call with an
 * argument missing rather than an argument that is a literal. So the invalid
 * bytes become the replacement character, and a refusal for any other reason
 * still yields a literal.
 */
function addtorrent_literal($value)
{
	$literal = json_encode(strval($value),
		JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_SLASHES|
		JSON_INVALID_UTF8_SUBSTITUTE);
	return($literal===false ? '""' : $literal);
}

// The result of one load: the hash it returned is a duplicate when it was
// loaded before this request, or by an earlier item of it.
function addtorrent_status($hash, &$loaded)
{
	if($hash===false)
		return("Failed");
	$hash = strtoupper($hash);
	if(isset($loaded[$hash]))
		return("Duplicate");
	$loaded[$hash] = true;
	return("Success");
}

if(isset($_REQUEST['result']))
{
	$results = is_array($_REQUEST['result']) ? array_values($_REQUEST['result']) : array($_REQUEST['result']);
	if(isset($_REQUEST['json']))
		CachedEcho::send( '{ "result" : '.addtorrent_literal(isset($results[0]) ? $results[0] : '').' }',
			"application/json");
	else
	{
		$names = (isset($_REQUEST['name']) && is_array($_REQUEST['name']))
			? array_values($_REQUEST['name']) : array();
		$js = '';
		foreach( $results as $ndx=>$result )
			$js.= ('noty('.addtorrent_literal(isset($names[$ndx]) ? ($names[$ndx].' - ') : '').
				'+theUILang["addTorrent"+'.addtorrent_literal($result).']'.
				','.addtorrent_literal(($result=='Success') ? 'success' : (($result=='Duplicate') ? 'alert' : 'error')).');');
		CachedEcho::send($js,"text/html");
	}
}
else
{
	$uploaded_files = array();
	$label = null;
	if(isset($_REQUEST['label']))
		$label = trim($_REQUEST['label']);
	$dir_edit = null;
	if(isset($_REQUEST['dir_edit']))
	{
		$dir_edit = trim($_REQUEST['dir_edit']);
		if((strlen($dir_edit)>0) && !rTorrentSettings::get()->correctDirectory($dir_edit))
			$uploaded_files = array( array( 'status' => "FailedDirectory" ) );
	}
	// No addition is taken from the request. An addition is an rtorrent command
	// appended to the load call, so accepting one here would let a request name
	// the commands the daemon runs. The parameter stays on rTorrent::sendTorrent()
	// and rTorrent::sendMagnet() for the plugins that build one in php.
	$addition = null;
	$loaded = rTorrent::loadedHashes();
	if(empty($uploaded_files))
	{
		if(isset($_FILES['torrent_file']))
		{
			if( is_array($_FILES['torrent_file']['name']) )
			{
				for ($i = 0; $i<count($_FILES['torrent_file']['name']); ++$i)
				{
		                        $files[] = array
        		                (
                		            'name' => $_FILES['torrent_file']['name'][$i],
                        		    'tmp_name' => $_FILES['torrent_file']['tmp_name'][$i],
		                        );
        	        	}
			}
			else
				$files[] = $_FILES['torrent_file'];
			foreach( $files as $file )
			{
				$ufile = $file['name'];
				if(pathinfo($ufile,PATHINFO_EXTENSION)!="torrent")
					$ufile.=".torrent";
				$ufile = FileUtil::getUniqueUploadedFilename($ufile);
				$ok = move_uploaded_file($file['tmp_name'],$ufile);
				$uploaded_files[] = array( 'name'=>$file['name'], 'file'=>$ufile, 'status'=>($ok ? "Success" : "Failed") );
			}
		}
		else
		{
			if(isset($_REQUEST['url']))
			{
				$urls = preg_split('/[\r\n]+/', trim($_REQUEST['url']));
				foreach($urls as $url)
				{
					$url = trim($url);
					if(empty($url)) continue;

					$uploaded_url = array( 'name'=>$url, 'status'=>"Failed" );
					if(strpos($url,"magnet:")===0)
					{
						$uploaded_url['status'] = addtorrent_status(rTorrent::sendMagnet($url,
							!isset($_REQUEST['torrents_start_stopped']),
							!isset($_REQUEST['not_add_path']),
							$dir_edit,$label,$addition), $loaded);
					}
					else
					{
						$cli = new Snoopy();
						if(@$cli->fetchComplex($url) && $cli->status>=200 && $cli->status<300)
						{
							$name = $cli->get_filename();
							if($name===false)
								$name = md5($url).".torrent";
							$name = FileUtil::getUniqueUploadedFilename($name);
							$f = @fopen($name,"w");
							if($f!==false)
							{
								@fwrite($f,$cli->results,strlen($cli->results));
								fclose($f);
								$uploaded_url['file'] = $name;
								$uploaded_url['status'] = "Success";
							}
						}
						else
							$uploaded_url['status'] = "FailedURL";
					}
					$uploaded_files[] = $uploaded_url;
				}
			}
		}
	}
	if (!empty($_SERVER['PHP_SELF']) && !empty($_SERVER['HTTP_HOST']))
		$location = "Location: //".$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF'])."/addtorrent.php?";
	else
		$location = "Location: ./addtorrent.php?";
	if(empty($uploaded_files))
		$uploaded_files = array( array( 'status' => "Failed" ) );
	foreach($uploaded_files as &$file)
	{
		if( ($file['status']=='Success') && isset($file['file']) )
		{
			$file['file'] = realpath($file['file']);
			@chmod($file['file'],$profileMask & 0666);
			$torrent = new Torrent($file['file']);
			if($torrent->errors())
			{
				@unlink($file['file']);
				$file['status'] = "FailedFile";
			}
			else
			{
				if(isset($_REQUEST['randomize_hash']))
					$torrent->info['unique'] = uniqid("rutorrent-",true);
				$file['status'] = addtorrent_status(rTorrent::sendTorrent($torrent,
					!isset($_REQUEST['torrents_start_stopped']),
					!isset($_REQUEST['not_add_path']),
					$dir_edit,$label,$saveUploadedTorrents,isset($_REQUEST['fast_resume']),true,$addition), $loaded);
				if($file['status']!='Success')
					@unlink($file['file']);
			}
		}
		$location.=('result[]='.$file['status'].'&');
		if( isset($file['name']) )
			$location.=('name[]='.rawurlencode($file['name']).'&');
	}
	header("HTTP/1.0 302 Moved Temporarily");
	if(isset($_REQUEST['json']))
		$location.='json=1';
	header($location);
}
