<?php

require_once( 'util.php' );
require_once( 'xmlrpc.php' );
require_once( 'Torrent.php' );

class rTorrent
{
	const RTORRENT_PACKET_LIMIT = 1572864;

	static public function sendTorrent($fname, $isStart, $isAddPath, $directory, $label, $saveTorrent, $isFast, $isNew = true, $addition = null)
	{
		$hash = false;
		$mustSave = is_object($fname);
		$torrent = $mustSave ? $fname : new Torrent($fname);

		if(!$torrent->errors())
		{
			if($isFast && ($resume = self::fastResume($torrent, $directory, $isAddPath)))
			{
				$torrent = $resume;
				$mustSave = true;
			}
			else
			{
				if($isNew && isset($torrent->{'libtorrent_resume'}))
				{
					unset($torrent->{'libtorrent_resume'});
					$mustSave = true;
				}
			}
			if($isNew && isset($torrent->{'rtorrent'}))
			{
				unset($torrent->{'rtorrent'});
				$mustSave = true;
			}
			$raw_value = base64_encode($torrent->__toString());
			$filename = is_object($fname) ? $torrent->getFileName() : $fname;
			if(strlen($raw_value)<self::RTORRENT_PACKET_LIMIT)
			{
				$cmd = new rXMLRPCCommand( $isStart ? 'load_raw_start' : 'load_raw' );
				$cmd->addParameter($raw_value,"base64");
				if(!is_null($filename) && !$saveTorrent)
					@unlink($filename);
			}
			else
			{
				if(!User::isLocalMode())
				{
					// we can't send torrent to the other host without FS sharing
					return(false);
				}
				if(is_null($filename))
				{
					$filename = FileUtil::getTempFilename($torrent->name(), 'torrent');
					$mustSave = true;
				}
				if($mustSave)
				{
					// because torrent may be changed in memory
					$torrent->save($filename);
				}
				$cmd = new rXMLRPCCommand( $isStart ? 'load_start' : 'load' );
				$cmd->addParameter($filename);
			}
			if(!is_null($filename) && (rTorrentSettings::get()->iVersion>=0x805))
				$cmd->addParameter(getCmd("d.set_custom")."=x-filename,".rawurlencode(FileUtil::getFileName($filename)));
			$req = new rXMLRPCRequest();
			$directory = self::parseDirectory($directory, $isAddPath);
			if($directory && (strlen($directory)>0))
			{
				if(!rTorrentSettings::get()->correctDirectory($directory))
					return(false);
				$req->addCommand( new rXMLRPCCommand( 'execute', array('mkdir','-p',$directory) ) );
				$cmd->addParameter( ($isAddPath ? getCmd("d.set_directory=")."\"" : getCmd("d.set_directory_base=")."\"").$directory."\"" );
			}
			$comment = $torrent->comment();
			if($comment)
			{
				if(UTF::isInvalidUTF8($comment))
					$comment = UTF::win2utf($comment);
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
			$req->addCommand( $cmd );
			if($req->run() && !$req->fault)
				$hash = $torrent->hash_info();
		}
		return($hash);
	}

	static public function sendMagnet($magnet, $isStart, $isAddPath, $directory, $label, $addition = null)
	{
	        $hpos = stripos($magnet,'xt=urn:btih:');
	        if($hpos!==false)
	        {
	        	$hpos+=12;
	        	$fpos = stripos($magnet,'&',$hpos);
			if($fpos===false)
				$fpos = strlen($magnet);
			$hash = strtoupper(substr($magnet,$hpos,$fpos-$hpos));
                        if(strlen($hash)==32)
		        	$hash = Decode::base32decode($hash);
	        	if(strlen($hash)==40)
	        	{
				$req = new rXMLRPCRequest();
				$cmd = new rXMLRPCCommand( $isStart ? 'load_start' : 'load' );
				$cmd->addParameter($magnet);
				$directory = self::parseDirectory($directory, $isAddPath);
				if($directory && (strlen($directory)>0))
				{
					if(!rTorrentSettings::get()->correctDirectory($directory))
						return(false);
					$cmd->addParameter( ($isAddPath ? getCmd("d.set_directory=")."\"" : getCmd("d.set_directory_base=")."\"").$directory."\"" );
					$req->addCommand( new rXMLRPCCommand( 'execute', array('mkdir','-p',$directory) ) );
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
				$req->addCommand( $cmd );
				if($req->success())
					return($hash);
			}
		}
		return(false);
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
	        	$base = rTorrentSettings::get()->directory;
		}
	        if($psize && rTorrentSettings::get()->correctDirectory($base))
	        {
		        $base = FileUtil::addslash($base);
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

	static protected function parseDirectory($directory, $isAddPath)
	{
		if(!$isAddPath && (!$directory || (strlen($directory)==0)))
		{
			$directory = rTorrentSettings::get()->directory;
		}
		return($directory);
	}
}
