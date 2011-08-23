<?php

require_once( 'util.php' );
require_once( 'xmlrpc.php' );
require_once( 'Torrent.php' );

class rTorrent
{
	static public function sendTorrent($fname, $isStart, $isAddPath, $directory, $label, $saveTorrent, $isFast, $isNew = true, $addition = null)
	{
	        global $topDirectory;
		$hash = false;
		$torrent = is_object($fname) ? $fname : new Torrent($fname);
		if(!$torrent->errors())
		{
			if($isFast && ($resume = self::fastResume($torrent, $directory, $isAddPath)))
				$torrent = $resume;
			else
				if($isNew)
				{
					if(isset($torrent->{'libtorrent_resume'}))
						unset($torrent->{'libtorrent_resume'});
				}			
			if($isNew)
			{
				if(isset($torrent->{'rtorrent'}))
					unset($torrent->{'rtorrent'});
			}
			$cmd = new rXMLRPCCommand( $isStart ? 'load_raw_start' : 'load_raw' );
			$cmd->addParameter(base64_encode($torrent->__toString()),"base64");
			if(!is_object($fname) && (rTorrentSettings::get()->iVersion>=0x805))
				$cmd->addParameter(getCmd("d.set_custom")."=x-filename,".rawurlencode(getFileName($fname)));
				
			if(!$saveTorrent && is_string($fname))
				@unlink($fname);
			if($directory && (strlen($directory)>0))
			{
				if($directory=='~')
					$directory = rTorrentSettings::get()->home.substr($directory,1);
				if(strpos(addslash($directory),$topDirectory)!==0)
					return(false);
				$cmd->addParameter( ($isAddPath ? getCmd("d.set_directory=")."\"" : getCmd("d.set_directory_base=")."\"").$directory."\"" );
			}
			$comment = $torrent->comment();
			if($comment)
			{
				if(isInvalidUTF8($comment))
					$comment = win2utf($comment);
				if(strlen($comment)>0)
				{
					$comment = "VRS24mrker".rawurlencode($comment);
					if(strlen($comment)<=4096)
						$cmd->addParameter(getCmd("d.set_custom2=").$comment);
				}
			}
			if($label && (strlen($label)>0))
			{
				$label = rawurlencode($label);
				if(strlen($label)<=4096)
					$cmd->addParameter(getCmd("d.set_custom1=").$label);
			}
			if(is_array($addition))
				foreach($addition as $key=>$prm)
					$cmd->addParameter($prm,'string');
			$req = new rXMLRPCRequest( $cmd );
			if($req->run() && !$req->fault)
				$hash = $torrent->hash_info();
		}
		return($hash);
	}
	static public function sendMagnet($magnet, $isStart, $isAddPath, $directory, $label)
	{
	        global $topDirectory;
		$cmd = new rXMLRPCCommand( $isStart ? 'load_start' : 'load' );
		$cmd->addParameter($magnet);
		if($directory && (strlen($directory)>0))
		{
			if($directory=='~')
				$directory = rTorrentSettings::get()->home.substr($directory,1);
			if(strpos(addslash($directory),$topDirectory)!==0)
				return(false);
			$cmd->addParameter( ($isAddPath ? getCmd("d.set_directory=")."\"" : getCmd("d.set_directory_base=")."\"").$directory."\"" );
		}
		if($label && (strlen($label)>0))
		{
			$label = rawurlencode($label);
			if(strlen($label)<=4096)
				$cmd->addParameter(getCmd("d.set_custom1=").$label);
		}
		$req = new rXMLRPCRequest( $cmd );
		return($req->success());
	}
	static public function getSource($hash)
	{
		$req = new rXMLRPCRequest( array(		
			new rXMLRPCCommand("get_session"),
			new rXMLRPCCommand("d.get_tied_to_file",$hash)) );
		if($req->run() && !$req->fault)
		{
			$fname = $req->val[0].$hash.".torrent";
			if(empty($req->val[0]) || !is_readable($fname))
			{
				if(strlen($req->val[1]) && is_readable($req->val[1]))
					$fname = $req->val[1];
				else
					$fname = null;
			}
			if($fname)
			{
				$torrent = new Torrent( $fname );		
				if( !$torrent->errors() )
				{
					if(isset($torrent->{'libtorrent_resume'}))
						unset($torrent->{'libtorrent_resume'});
					if(isset($torrent->{'rtorrent'}))
						unset($torrent->{'rtorrent'});
					return($torrent);
				}
			}
		}
		return(false);
	}

	static public function fastResume($torrent, $base, $add_path = true)
	{
	        $files = array();
	        $info = $torrent->info;
	        $psize = intval($info['piece length']);
		$base = trim($base);
	        if($base=='')
	        {
	        	$req = new rXMLRPCRequest( new rXMLRPCCommand('get_directory') );
	        	if($req->success())
	        		$base=$req->val[0];
	        }
	        $base = addslash($base);
	        if($psize && ($base!=''))
	        {
	                $tsize = 0.0;
			if(isset($info['files']))
			{
				foreach($info['files'] as $key=>$file)
				{
				        $tsize+=floatval($file['length']);
					$files[] = ($add_path ? $info['name']."/".implode('/',$file['path']) : implode('/',$file['path']));
				}
			}
			else
			{
				$tsize = floatval($info['length']);
				$files[] = $info['name'];
			}
			$chunks = intval(($tsize + $psize - 1) / $psize);
			$torrent->{'libtorrent_resume'}['bitfield'] = intval($chunks);
			if(!isset($torrent->{'libtorrent_resume'}['files']))
				$torrent->{'libtorrent_resume'}['files'] = array();
			foreach($files as $key=>$file)
			{
				$ss = LFS::stat($base.$file);
				if($ss===false)
					return(false);
				if(count($torrent->{'libtorrent_resume'}['files'])<$key)
					$torrent->{'libtorrent_resume'}['files'][$key]['mtime'] = $ss["mtime"];
				else
					$torrent->{'libtorrent_resume'}['files'][$key] = array( "priority" => 2, "mtime" => $ss["mtime"] );
			}
			return($torrent);
		}
		return(false);
	}
}

?>