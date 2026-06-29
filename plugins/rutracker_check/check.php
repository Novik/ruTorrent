<?php

require_once( "../../php/Snoopy.class.inc" );
require_once( "../../php/rtorrent.php" );
require_once( "../../php/util.php" );

require_once( "trackers/rutracker.php" );
require_once( "trackers/anidub.php" );
require_once( "trackers/kinozal.php" );
require_once( "trackers/nnmclub.php" );
require_once( "trackers/tapocheknet.php" );
require_once( "trackers/tfile.php" );
require_once( "trackers/toloka.php" );

eval(FileUtil::getPluginConf( "rutracker_check" ));

class ruTrackerChecker
{
	const STE_INPROGRESS		= 1;
	const STE_UPDATED		= 2;
	const STE_UPTODATE		= 3;
	const STE_DELETED		= 4;
	const STE_CANT_REACH_TRACKER	= 5;
	const STE_ERROR			= 6;
	const STE_NOT_NEED		= 7;
	const STE_IGNORED		= 8;

	const MAX_LOCK_TIME		= 900;	// 15 min

	private static $TRACKERS = array();
	private static $ANNOUNCES = array();

	/**
	 * Register a tracker handler.
	 *
	 * @param string   $commentFilter  Regex pattern for torrent comment
	 * @param string   $announceFilter Regex pattern for announce URL list
	 * @param callable $handler        Handler function: handler($url, $hash, $torrent)
	 */
	static public function registerTracker($commentFilter, $announceFilter, $handler)
	{
		if(!array_key_exists($commentFilter, self::$TRACKERS))
		{
			self::$TRACKERS[$commentFilter] = $handler;
			self::$ANNOUNCES[] = $announceFilter;
		}
	}

	static public function supportedTrackers()
	{
		return(self::$ANNOUNCES);
	}

	static protected function setState( $hash, $state )
	{
		// First check if the torrent still exists in rTorrent
		// This prevents "info-hash not found" errors when the torrent was already
		// deleted (e.g., during replacement in createTorrent)
		$checkReq = new rXMLRPCRequest( new rXMLRPCCommand( getCmd("d.hash"), $hash ) );
		$checkReq->important = false;
		if(!$checkReq->run() || $checkReq->fault)
		{
			// Torrent doesn't exist anymore, skip setting state
			self::logDebug("setState: Torrent " . $hash . " not found, skipping state update");
			return(true);
		}

		$req = new rXMLRPCRequest( array(
			new rXMLRPCCommand( getCmd("d.set_custom"), array($hash, "chk-state", $state."")  ),
			new rXMLRPCCommand( getCmd("d.set_custom"), array($hash, "chk-time", time()."") )
			));
		if($state == self::STE_UPTODATE)
			$req->addCommand(new rXMLRPCCommand( getCmd("d.set_custom"), array($hash, "chk-stime", time()."") ));
		return($req->success());
	}

	static protected function getState( $hash, &$state, &$time, &$successful_time, &$label )
	{
		$req = new rXMLRPCRequest( array(
			new rXMLRPCCommand( getCmd("d.get_custom"), array($hash, "chk-state")  ),
			new rXMLRPCCommand( getCmd("d.get_custom"), array($hash, "chk-time") ),
			new rXMLRPCCommand( getCmd("d.get_custom"), array($hash, "chk-stime") ),
			new rXMLRPCCommand( getCmd("d.get_custom1"), $hash )
			));
		if($req->success())
		{
			$state = intval($req->val[0]);
			$time = intval($req->val[1]);
			$successful_time = intval($req->val[2]);
			$label = $req->val[3];
			return(true);
		}
		else
		{
			$state = self::STE_INPROGRESS;
			$time = time();
			$successful_time = 0;
			$label = "";
			return(false);
		}
	}

	// Build a list of relative file paths for a torrent (single-file or multi-file).
	// Used to detect renamed/missing files when swapping torrents.
	static private function collectTorrentPaths($torrent)
	{
		if(!is_object($torrent) || !isset($torrent->info))
			return array();

		$info = $torrent->info;
		$paths = array();

		// Multi-file mode
		if(isset($info['files']) && is_array($info['files']))
		{
			// Note: We do NOT prepend $info['name'] (the torrent root folder) here,
			// because d.get_directory_base already returns the path INCLUDING that folder.
			// If we added it, we'd get: /base/FolderName/FolderName/file.mkv (duplicate)
			foreach($info['files'] as $file)
			{
				if(!isset($file['path']) || !is_array($file['path']))
					continue;

				// Build relative path within the torrent folder (without the folder name prefix)
				$rel = implode('/', $file['path']);
				// Guard against path traversal
				if(strpos($rel,'..')!==false)
					continue;
				$paths[] = $rel;
			}
		}
		// Single-file mode
		elseif(isset($info['name']))
		{
			if(strpos($info['name'],'..')===false)
				$paths[] = $info['name'];
		}

		// Remove possible duplicates
		return array_values(array_unique($paths));
	}

	// Helper function to remove empty subdirectories recursively
	static private function removeEmptySubFolders($path, $baseAbs)
	{
		if(empty($path) || $path == $baseAbs)
			return;

		$dir = dirname($path);
		// Ensure we're still inside the base directory
		if(strpos(FileUtil::addslash($dir), $baseAbs) !== 0 || $dir == $baseAbs)
			return;

		if(is_dir($dir))
		{
			// scandir can return false (permissions, etc.), which causes TypeError in array_diff in PHP 8
			$scanned = @scandir($dir);
			if(is_array($scanned))
			{
				$files = array_diff($scanned, array('.', '..'));
				if(empty($files))
				{
					@rmdir($dir);
					// Recursively go up
					self::removeEmptySubFolders($dir, $baseAbs);
				}
			}
		}
	}

	// Remove files from the old torrent that are absent in the new one (to avoid duplicates after rename).
	// Runs only after the new torrent is successfully loaded and the old one erased.
	static private function cleanupObsoleteFiles($oldTorrent, $newTorrent, $baseDir)
	{
		self::logDebug("cleanupObsoleteFiles: Starting cleanup. BaseDir: " . $baseDir);

		if(empty($baseDir) || !is_object($oldTorrent) || !is_object($newTorrent)) {
			self::logDebug("cleanupObsoleteFiles: Invalid arguments or objects.");
			return;
		}

		$oldPaths = self::collectTorrentPaths($oldTorrent);
		if(empty($oldPaths)) {
			self::logDebug("cleanupObsoleteFiles: No files found in old torrent.");
			return;
		}

		$newPaths = self::collectTorrentPaths($newTorrent);
		$missing = array_diff($oldPaths, $newPaths);

		self::logDebug("cleanupObsoleteFiles: Old files count: " . count($oldPaths));
		self::logDebug("cleanupObsoleteFiles: New files count: " . count($newPaths));
		self::logDebug("cleanupObsoleteFiles: Missing files count: " . count($missing));

		if(empty($missing)) {
			self::logDebug("cleanupObsoleteFiles: No missing files to delete.");
			return;
		}

		$baseAbs = FileUtil::addslash(FileUtil::fullpath($baseDir));
		if(empty($baseAbs)) {
			self::logDebug("cleanupObsoleteFiles: Could not resolve absolute base path.");
			return;
		}
		self::logDebug("cleanupObsoleteFiles: Absolute base path: " . $baseAbs);

		foreach($missing as $relPath)
		{
			// Build an absolute path inside the data directory and ensure it doesn't escape it.
			$absolute = FileUtil::fullpath($relPath, $baseAbs);

			// Security check
			if(strpos(FileUtil::addslash($absolute), $baseAbs) !== 0) {
				self::logDebug("cleanupObsoleteFiles: Security check failed for path: " . $absolute);
				continue;
			}

			if(is_file($absolute))
			{
				self::logDebug("cleanupObsoleteFiles: Attempting to delete file: " . $absolute);
				if(@unlink($absolute))
				{
					self::logDebug("cleanupObsoleteFiles: Successfully deleted: " . $absolute);
					// Try to remove parent folder if it became empty
					self::removeEmptySubFolders($absolute, $baseAbs);
				} else {
					self::logDebug("cleanupObsoleteFiles: Failed to delete file (unlink returned false): " . $absolute);
				}
			} else {
				self::logDebug("cleanupObsoleteFiles: File not found or not a file: " . $absolute);
			}
		}
		self::logDebug("cleanupObsoleteFiles: Cleanup finished.");
	}

	static public function createTorrent($torrent, $hash){
		global $saveUploadedTorrents;
		$torrent = new Torrent( $torrent );

		if( $torrent->errors() ) return self::STE_DELETED;

		if( $torrent->hash_info()==$hash ) return self::STE_UPTODATE;

		// Keep the current torrent to compare file lists for cleanup after successful replacement.
		// If loading the new torrent fails, the old files remain untouched.
		$oldTorrent = rTorrent::getSource($hash);

		$req =  new rXMLRPCRequest( array(
			new rXMLRPCCommand("d.get_directory_base",$hash),
			new rXMLRPCCommand("d.get_custom1",$hash),
			new rXMLRPCCommand("d.get_throttle_name",$hash),
			new rXMLRPCCommand("d.get_connection_seed",$hash),
			new rXMLRPCCommand("d.is_open",$hash),
			new rXMLRPCCommand("d.is_active",$hash),
			new rXMLRPCCommand("d.get_state",$hash),
			new rXMLRPCCommand("d.stop",$hash),
			new rXMLRPCCommand("d.close",$hash),
		));

		if($req->success()){
			$baseDir = $req->val[0];
			$addition = array(
				getCmd("d.set_connection_seed=").$req->val[3],
				getCmd("d.set_custom")."=chk-state,".self::STE_UPDATED,
				getCmd("d.set_custom")."=chk-time,".time(),
				getCmd("d.set_custom")."=chk-stime,".time()
			);
			$isStart = (($req->val[4]!=0) && ($req->val[5]!=0) && ($req->val[6]!=0));
			if(!empty($req->val[2]))
				$addition[] = getCmd("d.set_throttle_name=").$req->val[2];
			// Preserve ratio-group view if it was set (values like "rat_1", "rat_5" etc).
			// Check if regex matched and index exists
			if(preg_match('/rat_(\d+)/',$req->val[3],$ratio) && isset($ratio[1]))
				$addition[] = getCmd("view.set_visible=")."rat_".$ratio[1];
			$label = rawurldecode($req->val[1]);
			if(rTorrent::sendTorrent($torrent, $isStart, false, $baseDir,
				$label, $saveUploadedTorrents, false, true, $addition))
			{
				$req = new rXMLRPCRequest( new rXMLRPCCommand("d.erase", $hash ) );
				if($req->success()){
					self::cleanupObsoleteFiles($oldTorrent, $torrent, $baseDir);
					// Successful .torrent replacement: new torrent state is already set via $addition
					return null;
				}
			}
		}
		return self::STE_ERROR;
	}

	static public function run_ex($hash, $fname){
		$torrent = new Torrent( $fname );
		if(!$torrent->errors()){
			// Get both announce URL and comment for matching
			$announce = $torrent->announce();
			$comment = $torrent->comment();

			foreach (self::$TRACKERS as $pattern => $handler)
			{
				$matchedUrl = null;

				// First check comment: usually contains topic URL (e.g., viewtopic.php?t=...)
				if( preg_match($pattern, $comment) )
				{
					$matchedUrl = $comment;
				}
				// If not found in comment, try announce
				elseif( preg_match($pattern, $announce) )
				{
					$matchedUrl = $announce;
				}

				if($matchedUrl !== null)
				{
					return call_user_func($handler, $matchedUrl, $hash, $torrent);
				}
			}
		}
		return self::STE_NOT_NEED;
	}

	/**
	 * Simple plugin logger.
	 * Writes to /tmp/rutracker_check.log
	 */
	static public function logDebug($message)
	{
		$logFile = '/tmp/rutracker_check.log';
		$logDir = dirname($logFile);

		// Protection: verify permissions before attempting to write
		$canWrite = file_exists($logFile) ? is_writable($logFile) : is_writable($logDir);

		if($canWrite)
		{
			$line = '[' . gmdate('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
			@file_put_contents($logFile, $line, FILE_APPEND);
		}
	}

	static public function makeClient( $url, $method="GET", $content_type="", $body="" )
	{
		$client = new Snoopy();
		$client->read_timeout = 5;
		$client->_fp_timeout  = 5;

		// Pretend to be a modern browser to reduce 403/anti-bot errors
		$client->agent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
		               . "AppleWebKit/537.36 (KHTML, like Gecko) "
		               . "Chrome/120.0.0.0 Safari/537.36";

		// Suppress Snoopy errors with @, but log status on failure
		@$client->fetchComplex($url, $method, $content_type, $body);

		// Convention: plugins consider status < 0 as "tracker unreachable"
		if($client->status < 0)
		{
			self::logDebug("Snoopy fetch failed: url=".$url." status=".$client->status);
		}

		return $client;
	}

	static public function run( $hash, $state = null, $time = null, $successful_time = null, $label = null )
	{
		global $ignoreLabels;

		if(is_null($state)) self::getState( $hash, $state, $time, $successful_time, $label );

		// Skip torrent if its label is in the ignore list
		if(!is_null($label) && isset($ignoreLabels) && is_array($ignoreLabels) && in_array($label, $ignoreLabels))
		{
			$state = self::STE_IGNORED;
			self::setState($hash, $state);
			return(true);
		}

		if(($state==self::STE_INPROGRESS) && ((time()-$time)>self::MAX_LOCK_TIME)) $state = 0;

		if($state!==self::STE_INPROGRESS){
			$state = self::STE_INPROGRESS;
			if(!self::setState( $hash, $state )) return(false);

			// Main path: via rTorrentSettings
			if(class_exists('rTorrentSettings') && method_exists('rTorrentSettings', 'get'))
			{
				$fname = rTorrentSettings::get()->session.$hash.".torrent";
			}
			else
			{
				// Fallback for non-standard configurations
				$fname = getSettingsPath().'/session/'.$hash.".torrent";
			}

			if(is_readable($fname))	$state = self::run_ex($hash, $fname);
			if($state==self::STE_INPROGRESS) $state=self::STE_ERROR;

			if(!is_null($state)) self::setState( $hash, $state );
		}
		return($state != self::STE_CANT_REACH_TRACKER);
	}

}
