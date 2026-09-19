<?php

require_once( 'util.php' );
require_once( 'xmlrpc.php' );
require_once( 'Torrent.php' );

class rTorrent
{
	const RTORRENT_PACKET_LIMIT = 1572864;

	// An addition is appended to the load call as a trailing parameter, and
	// rtorrent reads every parameter a load command carries after the torrent
	// as a command to run against the download being created. That is how the
	// directory below is set. So an addition is an rtorrent command, and the
	// set that may be given as one is the set the shipped plugins build:
	// throttle, view membership and connection type. Anything outside it is
	// refused -- 'execute' above all, which would run a program as the daemon's
	// user, but equally a second directory command, which would land after the
	// one correctDirectory() approved and replace it.
	//
	// This is a different boundary from the one rXMLRPCCommand draws. That one
	// governs the <methodName> element; an addition travels as a parameter, is
	// HTML-escaped on its way into the payload, and is read as a command by
	// rtorrent rather than by the XMLRPC layer.
	// d.set_custom and d.set_custom3 are on the list because the shipped
	// plugins build them: the edit and retrackers plugins mark a reloaded
	// torrent with d.set_custom3, and rutracker_check writes its
	// replacement marker and check state with d.set_custom. The other
	// custom fields are deliberately absent -- d.set_custom1 and
	// d.set_custom2 hold the label and the comment, which sendTorrent()
	// already writes from its own parameters, and nothing builds
	// d.set_custom4 or d.set_custom5 as an addition.
	const ADDITION_COMMANDS = array(
		'd.set_throttle_name',
		'd.set_connection_seed',
		'view.set_visible',
		'd.views.push_back_unique',
		'd.set_custom',
		'd.set_custom3',
	);

	/**
	 * Whether one addition may be sent. Each permitted name is compared both as
	 * written and as the running daemon's alias table spells it, because that is
	 * how a caller builds one: getCmd('d.set_throttle_name=').$name.
	 */
	static public function isValidAddition( $addition )
	{
		if(!is_string($addition))
			return(false);
		$eq = strpos($addition,'=');
		if($eq===false)
			return(false);
		$name = substr($addition,0,$eq);
		foreach(self::ADDITION_COMMANDS as $permitted)
			if(($name===$permitted) ||
				($name===rTorrentSettings::get()->getCommand($permitted)))
				return(true);
		return(false);
	}

	/**
	 * A list holding one addition that may not be sent sends none of itself: an
	 * add is one load call, so dropping the offending entry would still perform
	 * the rest of an operation that was not the one asked for.
	 */
	static protected function areValidAdditions( $addition )
	{
		if(is_null($addition))
			return(true);
		if(!is_array($addition))
			return(false);
		foreach($addition as $prm)
			if(!self::isValidAddition($prm))
				return(false);
		return(true);
	}

	static public function sendTorrent($fname, $isStart, $isAddPath, $directory, $label, $saveTorrent, $isFast, $isNew = true, $addition = null)
	{
		if(!self::areValidAdditions($addition))
			return(false);
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
				$cmd->addParameter( getCmd($isAddPath ? "d.set_directory=" : "d.set_directory_base=").self::quoteCommandArg($directory) );
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
		if(!self::areValidAdditions($addition))
			return(false);
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
					$cmd->addParameter( getCmd($isAddPath ? "d.set_directory=" : "d.set_directory_base=").self::quoteCommandArg($directory) );
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

	/**
	 * Quote a value for use inside an rtorrent command string.
	 *
	 * The commands handed to load/load_raw are not typed parameters: rtorrent
	 * parses them itself (src/rpc/parse.cc), ending a quoted argument at the
	 * first unescaped '"' and reading '\' as an escape. A value wrapped in
	 * quotes without escaping therefore ends the argument early -- a download
	 * directory holding a quote silently loses everything from that quote on,
	 * and the command around it stops parsing.
	 *
	 * Mirrors the escaping XMLRPCProxy::rebuildSafeLoadParam() applies to the
	 * command strings it rebuilds.
	 */
	static public function quoteCommandArg($value)
	{
		// The order of the pairs is what makes this correct, and it must not be
		// changed: str_replace() works through the arrays left to right and can
		// revisit a value an earlier pair inserted. Escaping the quote first
		// would double the backslash that escaping just added, leaving a live
		// quote behind it -- so the backslash pair has to come first.
		return('"'.str_replace(array('\\', '"'), array('\\\\', '\\"'), $value).'"');
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
