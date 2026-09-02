<?php

// The queue between the CLI erase entry point (erase.php) and the RPC work.
//
// erase.php may be fired for several hundred downloads in one sweep, and
// doing the RPC work inline made each firing a blocking client of a server
// that answers one request at a time: everything timed out, nothing was
// recorded, and nothing recorded meant the whole sweep came back on the
// caller's next pass, indefinitely. The queue enables each firing to do
// only the recording part of the erase request, represented by a local
// file write, which should not fail however busy the server is. Exactly
// one firing goes on to do the RPC work for everything queued, one download
// at a time.

if(!function_exists('erasedataQueueRequest'))
{
	// Record an erase request by writing a marker file.
	function erasedataQueueRequest($listPath, $hash, $force)
	{
		global $profileMask;
		if(!preg_match('/^[0-9A-Fa-f]{40}$/', $hash))
			return(false);
		if(($force!=="1") && ($force!=="2"))
			$force = "1";
		if(is_file($listPath."/".$hash.".list"))
      // Already collected, waiting for the garbage collector to apply it.
			return(true);
		$marker = $listPath."/".$hash.".pending";
		// Exclusive create: two firings racing on one hash cannot both conclude
		// they are first, and a caller that fires again before the queue is
		// drained is a no-op rather than a second request.
		$fp = @fopen($marker, "x");
		if($fp===false)
			return(is_file($marker));
		@fwrite($fp, $force."\n0\n");
		@fclose($fp);
		@chmod($marker, (isset($profileMask) ? $profileMask : 0666) & 0666);
		return(true);
	}
}

if(!function_exists('erasedataDrainQueue'))
{
	// Collect and erase everything queued, or return immediately if another
	// process is already doing so.
	//
	// Returns the number of hashes attempted, or -1 when another drainer holds
	// the lock.
	function erasedataDrainQueue($listPath, $maxAttempts = 10)
	{
    // Check the lock to see if another process has already taken responsibilty for draining the queue.
		$lock = @fopen($listPath."/drain.lock", "c");
		if($lock===false)
			return(-1);
		if(!@flock($lock, LOCK_EX | LOCK_NB))
		{
			fclose($lock);
			return(-1);
		}

		// Only the winner needs to talk to rtorrent, so only the winner pays for
		// loading the RPC layer. Everyone else has already returned above.
		require_once( dirname(__FILE__)."/../../php/xmlrpc.php" );
		require_once( dirname(__FILE__)."/removewithdata.php" );

		$attempted = array();
		$done = 0;
		while(true)
		{
			$batch = erasedataPendingHashes($listPath, $attempted);
			if(!count($batch))
				break;
			foreach($batch as $hash=>$force)
				$attempted[$hash] = true;
			$done += erasedataDrainOnce($listPath, $batch, $maxAttempts);
		}

		flock($lock, LOCK_UN);
		fclose($lock);
		return($done);
	}

	// The queue as it stands, minus anything this drainer has already tried.
	// Re-read every pass so requests arriving mid-drain are picked up by the
	// drainer already running rather than waiting for the caller's next sweep.
	function erasedataPendingHashes($listPath, $attempted)
	{
		$ret = array();
		$pending = @glob($listPath.'/*.pending');
		if(!is_array($pending))
			return($ret);
		sort($pending);
		foreach($pending as $item)
		{
			$hash = basename($item, '.pending');
			if(!preg_match('/^[0-9A-Fa-f]{40}$/', $hash))
			{
				@unlink($item);
				continue;
			}
			if(array_key_exists($hash, $attempted))
				continue;
			// Collected by the web UI's "Remove and delete data" between the
			// marker being written and this pass.
			if(is_file($listPath.'/'.$hash.'.list'))
			{
				@unlink($item);
				continue;
			}
			$lines = @file($item, FILE_IGNORE_NEW_LINES);
			$ret[$hash] = (is_array($lines) && isset($lines[0]) && ($lines[0]==="2")) ? "2" : "1";
		}
		return($ret);
	}

	function erasedataDrainOnce($listPath, $batch, $maxAttempts)
	{
		// Drop what rtorrent no longer has before asking about it, so a marker
		// left behind for a download that was deleted some other way (the web
		// UI, say) is not retried into the give-up log.
		$live = erasedataLiveHashes();
		if(is_array($live))
		{
			foreach(array_keys($batch) as $hash)
				if(!array_key_exists(strtoupper($hash), $live))
				{
					@unlink($listPath.'/'.$hash.'.pending');
					unset($batch[$hash]);
				}
		}
		if(!count($batch))
			return(0);

    // Split the batch into at most two arrays, one for each force mode (1 and 2).
		$byForce = array("1"=>array(), "2"=>array());
		foreach($batch as $hash=>$force)
			$byForce[$force][] = $hash;
		foreach($byForce as $force=>$hashes)
			if(count($hashes))
				erasedataRemoveWithData($hashes, $force);

    // Clear the successful deletions from the queue by checking for the .list file
    // that is created by erasedataRemoveWithData() when it succeeds.
		foreach($batch as $hash=>$force)
		{
			$marker = $listPath.'/'.$hash.'.pending';
			// Once the .list exists, the garbage collector finishes the deletion
			if(is_file($listPath.'/'.$hash.'.list'))
			{
				@unlink($marker);
				continue;
			}
			$lines = @file($marker, FILE_IGNORE_NEW_LINES);
			$attempts = (is_array($lines) && isset($lines[1])) ? intval($lines[1])+1 : 1;
			if(($maxAttempts>0) && ($attempts>=$maxAttempts))
			{
				// Giving up leaves the download in place, data intact and still
				// identified by its own torrent, which is the safe end state.
				FileUtil::toLog("erasedata: giving up on ".$hash." after ".$attempts.
					" attempts, torrent not erased");
				@unlink($marker);
			}
			else
				@file_put_contents($marker, $force."\n".$attempts."\n");
		}
		return(count($batch));
	}

	// The hashes rtorrent currently holds, or null when it will not say.
	function erasedataLiveHashes()
	{
		$req = new rXMLRPCRequest( new rXMLRPCCommand("d.multicall",
			array("default", getCmd("d.get_hash="))) );
		$req->important = false;
		if(!$req->success())
			return(null);
		$ret = array();
		foreach($req->val as $hash)
			$ret[strtoupper($hash)] = true;
		return($ret);
	}
}
