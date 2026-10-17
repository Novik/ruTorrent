<?php

// Shared "remove with data" logic used by both the httprpc RPC handler and the
// direct (non-httprpc) endpoint plugins/erasedata/action.php. Reads the list
// of files to delete over RPC, which works on every rtorrent version, erases
// each torrent, and records its list into the erasedata list directory for
// the garbage collector once rtorrent has confirmed the erase. The caller
// must have already loaded php/xmlrpc.php.
if(!function_exists('erasedataCollectPaths'))
{
	// d.base_path and f.frozen_path are only filled in when rtorrent opens a
	// download's file list, and are not restored from the session. A download
	// that has not been opened since rtorrent started -- any torrent that was
	// stopped when the session was loaded -- reports both as empty, so fall
	// back to d.directory and f.path, which are always available.
	function erasedataCollectPaths($hash)
	{
		// rXMLRPCRequest flattens every returned value into ->val, so query one
		// torrent per request, and keep the variable-length f.multicall last:
		// val[0] = directory, val[1] = is_multi, val[2..] = each file path.
		$frozen = new rXMLRPCRequest( array(
			new rXMLRPCCommand( getCmd("d.get_base_path"), $hash ),
			new rXMLRPCCommand( getCmd("d.is_multi_file"), $hash ),
			new rXMLRPCCommand( getCmd("f.multicall"), array($hash, "", getCmd("f.get_frozen_path")."=") )
		) );
		if($frozen->success() && count($frozen->val) >= 3)
		{
			$files = array();
			foreach(array_slice($frozen->val, 2) as $path)
				if(strlen($path))
					$files[] = $path;
			if(count($files))
				return( array(
					"base"  => $frozen->val[0],
					"multi" => $frozen->val[1] ? "1" : "0",
					"files" => $files ) );
		}

		$stored = new rXMLRPCRequest( array(
			new rXMLRPCCommand( getCmd("d.get_directory"), $hash ),
			new rXMLRPCCommand( getCmd("d.is_multi_file"), $hash ),
			new rXMLRPCCommand( getCmd("f.multicall"), array($hash, "", getCmd("f.get_path")."=") )
		) );
		if(!$stored->success() || count($stored->val) < 3)
			return(false);
		$dir = rtrim($stored->val[0], '/');
		if(!strlen($dir))
			return(false);
		$isMulti = $stored->val[1] ? "1" : "0";
		$files = array();
		foreach(array_slice($stored->val, 2) as $path)
			if(strlen($path))
				$files[] = $dir.'/'.$path;
		if(!count($files))
			return(false);
		// d.directory is the download's root directory, which for a single-file
		// torrent is the directory holding the file, not the file itself --
		// d.base_path returns the file. Mirror that here.
		return( array(
			"base"  => $isMulti=="1" ? $dir : $files[0],
			"multi" => $isMulti,
			"files" => $files ) );
	}
}
if(!function_exists('erasedataStageList'))
{
	// Write the list into the list directory under a temporary name, which
	// the collector does not read, and answer that name -- or false, having
	// left nothing behind.
	//
	// The list is what makes the erase recoverable: once the torrent is gone,
	// it is the only thing left that names the download's files. So it is
	// written in full before the erase is attempted, every step of writing it
	// is checked, and a failure keeps the torrent: a full disk or a directory
	// that cannot be written would otherwise erase the download and leave its
	// data behind with nothing to identify it. The collector sees the list
	// only once erasedataPublishList() has renamed it, after the erase.
	function erasedataStageList($listPath, $hash, $contents)
	{
		$tmp = $listPath."/".$hash.".list2.".getmypid().".".uniqid('', true).".tmp";
		$fp = @fopen($tmp, "wb");
		if($fp===false)
			return(false);
		$written = @fwrite($fp, $contents);
		$ok = ($written === strlen($contents)) && @fflush($fp);
		if(@fclose($fp)===false)
			$ok = false;
		if($ok)
			return($tmp);
		@unlink($tmp);
		return(false);
	}
}
if(!function_exists('erasedataPublishList'))
{
	// Put a staged list where the collector will find it, or remove it and
	// publish nothing.
	//
	// Renamed into place rather than written there, because the collector is
	// its own process running on rtorrent's schedule and may read the
	// directory at any moment. It reads the base path, the multi-file flag
	// and the deletion mode from the last three lines, so a list that is half
	// written is not a short list: it is a different one, naming a base of
	// its own.
	//
	// The name carries the current format, ".list2". A ".list" is what the
	// writer that preceded the line-break refusal produced, and update.php
	// tells the two apart by that name alone.
	function erasedataPublishList($listPath, $hash, $staged)
	{
		if(@rename($staged, $listPath."/".$hash.".list2"))
			return(true);
		@unlink($staged);
		return(false);
	}
}
if(!function_exists('erasedataRemoveWithData'))
{
	function erasedataRemoveWithData($hashes, $forceDelete)
	{
		$listPath = FileUtil::getSettingsPath()."/erasedata";
		@FileUtil::makeDirectory($listPath);
		$result = array();
		$erased = 0;
		$failed = false;
		foreach($hashes as $h)
		{
			$paths = erasedataCollectPaths($h);
			if($paths === false)
			{
				// Erasing now would drop the torrent and leave its data behind
				// with nothing left to identify it, so keep the torrent.
				FileUtil::toLog("erasedata: could not determine the files of ".$h.", torrent not erased");
				continue;
			}
			$lines = $paths["files"];
			$lines[] = $paths["base"];
			$lines[] = $paths["multi"];
			$lines[] = $forceDelete;
			// The list is newline-delimited and read back a line at a time, so
			// a path carrying a line break arrives at the collector as more
			// than one entry, and every entry is a file it unlinks.
			//
			// A path element is whatever the torrent's publisher put in it.
			// libtorrent accepts anything that is not empty, not "." or "..",
			// and carries no '/' and no NUL (Path::is_valid_component in
			// src/torrent/path.cc, and is_valid_path_element in the older
			// src/download/download_constructor.cc): a line break is allowed,
			// and rtorrent reports the path back with it intact. One element
			// ending in a line break, with the elements after it supplying the
			// separators, names an absolute path of the publisher's choosing
			// starting at column 0 of the next line.
			//
			// A line break also moves the last three lines, which are what the
			// collector reads the base path, the multi-file flag and the
			// deletion mode from, so a crafted name can decide those too.
			//
			// Refused rather than escaped: a download whose file names carry
			// line breaks is not one this can clean up, and saying so and
			// keeping the torrent is what an unresolvable file list already
			// does above. Escaping would have to be understood by a collector
			// that may still be the previous one during an upgrade.
			$broken = false;
			foreach($lines as $line)
				if(strpbrk($line, "\r\n") !== false)
					$broken = true;
			if($broken)
			{
				FileUtil::toLog("erasedata: a path of ".$h." contains a line break, torrent not erased");
				continue;
			}
			// Staged before the erase, so that a list which cannot be written
			// keeps the torrent, and published after it, so that a list the
			// collector can read means an erase rtorrent has confirmed: the
			// collector deletes what a list names once rtorrent no longer
			// lists the hash, so a list must only exist for an erase rtorrent
			// confirmed. The paths are read first because rtorrent forgets
			// them once the download is erased.
			$staged = erasedataStageList($listPath, $h, implode("\n", $lines)."\n");
			if($staged === false)
			{
				FileUtil::toLog("erasedata: could not record the files of ".$h.", torrent not erased");
				continue;
			}
			// One request per download, so that what rtorrent confirms is this
			// download's erase and no other's: rXMLRPCRequest reads a reply as
			// one flat list of values and stops at the first fault, so a batch
			// could only ever be confirmed whole.
			$req = new rXMLRPCRequest( array(
				new rXMLRPCCommand( getCmd("d.set_custom5"), array($h, "") ),
				new rXMLRPCCommand( getCmd("d.delete_tied"), $h ),
				new rXMLRPCCommand( getCmd("d.erase"), $h ) ) );
			if(!$req->success())
			{
				// No reply, or a fault. rtorrent may still hold the download,
				// or may have erased it before the reply was lost; either way
				// the staged list goes, so the collector has nothing to act on.
				// A download that was erased after all keeps its data on disk,
				// which is the recoverable side, and the log says where.
				@unlink($staged);
				FileUtil::toLog("erasedata: rtorrent did not confirm the erase of ".$h.
					", nothing recorded for the collector; its data is at ".$paths["base"]);
				$failed = true;
				continue;
			}
			// The erase is confirmed and the torrent is gone. A crash between
			// here and the rename leaves the staged list unread and the data on
			// disk, which is the same recoverable side.
			if(!erasedataPublishList($listPath, $h, $staged))
			{
				FileUtil::toLog("erasedata: ".$h." was erased but its files could not be recorded".
					"; its data is left in place at ".$paths["base"]);
				$failed = true;
				continue;
			}
			$erased++;
			$result = array_merge($result, $req->val);
		}
		// As before: the values rtorrent answered, or false when a removal
		// did not happen.
		return (($erased > 0) && !$failed) ? $result : false;
	}
}
