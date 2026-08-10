<?php

// Shared "remove with data" logic used by both the httprpc RPC handler and the
// direct (non-httprpc) endpoint plugins/erasedata/action.php. Records the list
// of files to delete -- read over RPC, which works on every rtorrent version --
// into the erasedata list directory for the garbage collector, then erases the
// torrents. The caller must have already loaded php/xmlrpc.php.
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
if(!function_exists('erasedataRemoveWithData'))
{
	function erasedataRemoveWithData($hashes, $forceDelete)
	{
		$listPath = FileUtil::getSettingsPath()."/erasedata";
		@FileUtil::makeDirectory($listPath);
		$erasable = array();
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
			@file_put_contents($listPath."/".$h.".list", implode("\n", $lines)."\n");
			$erasable[] = $h;
		}
		if(!count($erasable))
			return(false);
		$req = new rXMLRPCRequest();
		foreach($erasable as $h)
		{
			$req->addCommand( new rXMLRPCCommand( getCmd("d.set_custom5"), array($h, "") ) );
			$req->addCommand( new rXMLRPCCommand( getCmd("d.delete_tied"), $h ) );
			$req->addCommand( new rXMLRPCCommand( getCmd("d.erase"), $h ) );
		}
		return $req->success() ? $req->val : false;
	}
}
