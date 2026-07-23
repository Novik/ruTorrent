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

	// load_raw inserts the torrent from a deferred rTorrent event-loop task,
	// so waiting for the staged copy to appear is the only wait in the
	// replacement transaction; every other command is synchronous.
	const LOAD_WAIT_ATTEMPTS	= 40;
	const LOAD_WAIT_DELAY_US	= 50000;
	const REPLACEMENT_MARKER_KEY	= 'chk-replacement';

	const USER_AGENT = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
		. "AppleWebKit/537.36 (KHTML, like Gecko) "
		. "Chrome/120.0.0.0 Safari/537.36";

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
			self::$TRACKERS[$commentFilter] = array(
				'announceFilter' => $announceFilter,
				'handler' => $handler,
			);
			self::$ANNOUNCES[] = $announceFilter;
		}
	}

	static public function supportedTrackers()
	{
		return(self::$ANNOUNCES);
	}

	/**
	 * Check whether rTorrent still knows a hash.
	 *
	 * @return bool|null true when present, false when the target is missing,
	 *                   null when the XMLRPC request itself failed
	 */
	static protected function torrentExists( $hash )
	{
		$req = new rXMLRPCRequest( new rXMLRPCCommand( getCmd("d.hash"), $hash ) );
		$req->important = false;
		if(!$req->run())
			return(null);
		return(!$req->fault);
	}

	static protected function setState( $hash, $state )
	{
		$req = new rXMLRPCRequest( array(
			new rXMLRPCCommand( getCmd("d.set_custom"), array($hash, "chk-state", $state."")  ),
			new rXMLRPCCommand( getCmd("d.set_custom"), array($hash, "chk-time", time()."") )
			));
		if($state == self::STE_UPTODATE)
			$req->addCommand(new rXMLRPCCommand( getCmd("d.set_custom"), array($hash, "chk-stime", time()."") ));
		$req->important = false;
		if($req->success())
			return(true);

		// The write faults when the hash is unknown; only then is the miss final.
		$exists = self::torrentExists($hash);
		if($exists === false)
		{
			self::logDebug("setState: Torrent " . $hash . " not found, skipping state update");
			return(null);
		}
		return(false);
	}

	static protected function getState( $hash, &$state, &$time, &$successful_time, &$label )
	{
		$state = self::STE_INPROGRESS;
		$time = time();
		$successful_time = 0;
		$label = "";

		$exists = self::torrentExists($hash);
		if($exists === false)
		{
			$state = self::STE_NOT_NEED;
			self::logDebug("getState: Torrent " . $hash . " not found, skipping state read");
			return(false);
		}
		if($exists === null)
			return(false);

		$req = new rXMLRPCRequest( array(
			new rXMLRPCCommand( getCmd("d.get_custom"), array($hash, "chk-state")  ),
			new rXMLRPCCommand( getCmd("d.get_custom"), array($hash, "chk-time") ),
			new rXMLRPCCommand( getCmd("d.get_custom"), array($hash, "chk-stime") ),
			new rXMLRPCCommand( getCmd("d.get_custom1"), $hash )
			));
		$req->important = false;
		if(!$req->success())
			return(false);

		$state = intval($req->val[0]);
		$time = intval($req->val[1]);
		$successful_time = intval($req->val[2]);
		$label = $req->val[3];
		return(true);
	}

	// Build a list of safe relative file paths for a torrent.
	static private function collectTorrentPaths($torrent)
	{
		if(!is_object($torrent) || !isset($torrent->info))
			return(null);

		$info = $torrent->info;
		if(!is_array($info))
			return(null);
		$paths = array();

		if(isset($info['files']) && is_array($info['files']))
		{
			foreach($info['files'] as $file)
			{
				if(!isset($file['path']) || !is_array($file['path']))
					return(null);
				$path = self::makeSafeRelativePath($file['path']);
				if($path === null)
					return(null);
				$paths[] = $path;
			}
		}
		elseif(isset($info['name']))
		{
			$path = self::makeSafeRelativePath(array($info['name']));
			if($path === null)
				return(null);
			$paths[] = $path;
		}

		return array_values(array_unique($paths));
	}

	static private function makeSafeRelativePath($components)
	{
		$normalized = array();
		foreach($components as $component)
		{
			if(!is_string($component) && !is_numeric($component))
				return(null);
			$component = (string) $component;
			if($component === '' || $component === '.' || $component === '..' ||
				strpos($component, "\0") !== false || strpos($component, '/') !== false ||
				strpos($component, '\\') !== false)
				return(null);
			$normalized[] = $component;
		}
		return(count($normalized) ? implode('/', $normalized) : null);
	}

	static private function removeEmptySubFolders($path, $baseAbs)
	{
		$dir = dirname($path);
		$basePrefix = FileUtil::addslash($baseAbs);
		while($dir !== $baseAbs && strpos(FileUtil::addslash($dir), $basePrefix) === 0)
		{
			if(is_link($dir) || realpath($dir) !== $dir)
				return;
			$scanned = @scandir($dir);
			if(!is_array($scanned) || count(array_diff($scanned, array('.', '..'))) || !@rmdir($dir))
				return;
			$dir = dirname($dir);
		}
	}

	// Remove files from the old torrent that are absent in the new one (to avoid duplicates after rename).
	// Runs only after the new torrent is successfully loaded and the old one erased.
	static private function cleanupObsoleteFiles($oldTorrent, $newTorrent, $baseDir)
	{
		if(empty($baseDir))
			return;

		$oldPaths = self::collectTorrentPaths($oldTorrent);
		$newPaths = self::collectTorrentPaths($newTorrent);
		if(empty($oldPaths) || empty($newPaths))
		{
			self::logDebug("cleanupObsoleteFiles: Missing a safe file manifest, skipping cleanup");
			return;
		}
		$missing = array_diff($oldPaths, $newPaths);
		self::logDebug("cleanupObsoleteFiles: Old " . count($oldPaths) . ", new " . count($newPaths)
			. ", missing " . count($missing) . " in " . $baseDir);
		if(empty($missing))
			return;

		$baseAbs = realpath($baseDir);
		// A torrent rooted at the filesystem root is too broad a cleanup scope.
		if($baseAbs === false || !is_dir($baseAbs) || $baseAbs === DIRECTORY_SEPARATOR)
		{
			self::logDebug("cleanupObsoleteFiles: Unusable base path, skipping cleanup");
			return;
		}
		$baseAbs = rtrim($baseAbs, DIRECTORY_SEPARATOR);
		$basePrefix = FileUtil::addslash($baseAbs);

		// On a case-insensitive or normalizing filesystem a renamed-away path can
		// alias the very file the new torrent references; a byte-wise path diff
		// cannot see that, so compare inodes before deleting anything.
		$newFileIds = array();
		foreach($newPaths as $relPath)
		{
			$stat = @stat($basePrefix . $relPath);
			if(is_array($stat) && !empty($stat['ino']))
				$newFileIds[$stat['dev'] . ':' . $stat['ino']] = true;
		}

		foreach($missing as $relPath)
		{
			$candidate = $basePrefix . $relPath;
			$absolute = realpath($candidate);
			// Reject missing paths, symlinks (including a symlinked parent), and escapes.
			if($absolute === false || $absolute !== $candidate || is_link($candidate) ||
				strpos(FileUtil::addslash($absolute), $basePrefix) !== 0)
			{
				self::logDebug("cleanupObsoleteFiles: Security check failed for path: " . $candidate);
				continue;
			}
			if(!is_file($absolute))
				continue;

			$stat = @stat($absolute);
			if(is_array($stat) && !empty($stat['ino']) && isset($newFileIds[$stat['dev'] . ':' . $stat['ino']]))
			{
				self::logDebug("cleanupObsoleteFiles: Path aliases a file of the new torrent, keeping: " . $absolute);
				continue;
			}

			if(@unlink($absolute))
				self::removeEmptySubFolders($absolute, $baseAbs);
			else
				self::logDebug("cleanupObsoleteFiles: Failed to delete file: " . $absolute);
		}
	}

	static private function buildReplacementAddition($connectionSeed, $throttle, $ratioViews, $state, $marker)
	{
		$now = time();
		$addition = array(
			getCmd("d.set_custom")."=".self::REPLACEMENT_MARKER_KEY.",".$marker,
			getCmd("d.set_connection_seed=").$connectionSeed,
			getCmd("d.set_custom")."=chk-state,".$state,
			getCmd("d.set_custom")."=chk-time,".$now,
			getCmd("d.set_custom")."=chk-stime,".$now,
		);
		if(!empty($throttle))
			$addition[] = getCmd("d.set_throttle_name=").$throttle;
		foreach($ratioViews as $ratioView)
			$addition[] = getCmd("view.set_visible=").$ratioView;
		return($addition);
	}

	/**
	 * Wait for the staged torrent to be inserted by rTorrent's deferred load.
	 * The addition commands run in the same event-loop step as the insert, so
	 * once the hash resolves, the marker is authoritative.
	 *
	 * @return string 'ours' | 'foreign' | 'missing'
	 */
	static private function waitForLoad($hash, $marker)
	{
		for($attempt = 0; $attempt < self::LOAD_WAIT_ATTEMPTS; $attempt++)
		{
			if($attempt)
				usleep(self::LOAD_WAIT_DELAY_US);
			$req = new rXMLRPCRequest( new rXMLRPCCommand(
				getCmd("d.get_custom"), array($hash, self::REPLACEMENT_MARKER_KEY) ) );
			$req->important = false;
			if(!$req->run() || $req->fault)
				continue;
			return((isset($req->val[0]) && (string) $req->val[0] === (string) $marker) ? 'ours' : 'foreign');
		}
		return('missing');
	}

	// Erase a hash that was verified to carry our replacement marker.
	static private function eraseStaged($hash)
	{
		$req = new rXMLRPCRequest( new rXMLRPCCommand("d.erase", $hash) );
		$req->important = false;
		if($req->success())
			return(true);
		return(self::torrentExists($hash) === false);
	}

	static private function restoreExistingTorrent($hash, $wasOpen, $wasStarted)
	{
		if(!$wasOpen && !$wasStarted)
			return(true);
		$restore = new rXMLRPCRequest( new rXMLRPCCommand($wasStarted ? "d.start" : "d.open", $hash) );
		$restore->important = false;
		return($restore->success());
	}

	// Runs after the commit point: failures are logged and the replacement is
	// left stopped rather than reported as a failed check.
	static private function activateReplacement($hash, $wasOpen, $wasStarted)
	{
		if(!$wasOpen && !$wasStarted)
			return(true);
		for($attempt = 0; $attempt < 2; $attempt++)
		{
			self::restoreExistingTorrent($hash, $wasOpen, $wasStarted);
			$check = new rXMLRPCRequest( array(
				new rXMLRPCCommand("d.get_state", $hash),
				new rXMLRPCCommand("d.is_open", $hash),
			));
			$check->important = false;
			if(!$check->success() || !isset($check->val[1]))
				continue;
			// A started torrent may stay closed until the scheduler grants a slot.
			if($wasStarted ? (intval($check->val[0]) === 1) : (intval($check->val[1]) === 1))
				return(true);
		}
		self::logDebug("activateReplacement: Could not confirm activation of " . $hash);
		return(false);
	}

	static private function clearReplacementMarker($hash)
	{
		$req = new rXMLRPCRequest( new rXMLRPCCommand(
			getCmd("d.set_custom"), array($hash, self::REPLACEMENT_MARKER_KEY, "")
		) );
		$req->important = false;
		$req->success();
	}

	static public function createTorrent($torrent, $hash){
		global $saveUploadedTorrents;
		// PHP 7.4 warns when Torrent probes binary metainfo as a filename.
		$torrent = @new Torrent( $torrent );

		// Legacy handlers feed HTTP-200 "topic removed" HTML pages straight in
		// here and rely on a parse failure meaning the topic is gone.
		if( $torrent->errors() ) return self::STE_DELETED;

		$newHash = $torrent->hash_info();
		if( $newHash==$hash ) return self::STE_UPTODATE;

		$exists = self::torrentExists($newHash);
		if($exists === null) return self::STE_ERROR;
		if($exists === true)
		{
			// A staged copy abandoned by a crashed run still carries a marker
			// (it is only cleared on success) and is always stopped and closed;
			// discard it and redo the replacement. Anything unmarked is foreign
			// and must not be touched.
			$markerReq = new rXMLRPCRequest( array(
				new rXMLRPCCommand(getCmd("d.get_custom"), array($newHash, self::REPLACEMENT_MARKER_KEY)),
				new rXMLRPCCommand("d.get_state", $newHash),
				new rXMLRPCCommand("d.is_open", $newHash),
			) );
			$markerReq->important = false;
			if(!$markerReq->success() || !isset($markerReq->val[2]) || (string) $markerReq->val[0] === '')
				return self::STE_ERROR;
			if(intval($markerReq->val[1]) !== 0 || intval($markerReq->val[2]) !== 0)
			{
				// A running torrent with a leftover marker is a committed
				// replacement whose final marker clear was lost; repair the
				// marker, but never treat a live torrent as disposable.
				self::clearReplacementMarker($newHash);
				return self::STE_ERROR;
			}
			if(!self::eraseStaged($newHash))
				return self::STE_ERROR;
		}

		// Keep the old metainfo for post-replacement file cleanup.
		$oldTorrent = rTorrent::getSource($hash);
		if(!is_object($oldTorrent)) return self::STE_ERROR;

		try
		{
			$marker = bin2hex(random_bytes(16));
		}
		catch(Exception $error)
		{
			return self::STE_ERROR;
		}

		// Ratio-group membership lives in rat_N views (see plugins/ratio).
		$viewsReq = new rXMLRPCRequest( new rXMLRPCCommand(getCmd("d.views"), $hash) );
		$viewsReq->important = false;
		if(!$viewsReq->success()) return self::STE_ERROR;
		$ratioViews = array();
		foreach($viewsReq->val as $view)
			if(is_string($view) && preg_match('/^rat_\d+$/', $view))
				$ratioViews[$view] = true;
		$ratioViews = array_keys($ratioViews);

		// Snapshot the logical state and stop/close it in the same multicall so a
		// scheduler action cannot slip between the read and the mutation.
		$req = new rXMLRPCRequest( array(
			new rXMLRPCCommand("d.get_directory_base",$hash),
			new rXMLRPCCommand("d.get_custom1",$hash),
			new rXMLRPCCommand("d.get_throttle_name",$hash),
			new rXMLRPCCommand("d.get_connection_seed",$hash),
			new rXMLRPCCommand("d.get_state",$hash),
			new rXMLRPCCommand("d.is_open",$hash),
			new rXMLRPCCommand("d.stop",$hash),
			new rXMLRPCCommand("d.close",$hash),
		));
		$req->important = false;
		if(!$req->success() || !isset($req->val[5]))
		{
			if(isset($req->val[5]))
				self::restoreExistingTorrent($hash, $req->val[5] != 0, $req->val[4] != 0);
			return self::STE_ERROR;
		}

		$baseDir = $req->val[0];
		$label = rawurldecode($req->val[1]);
		$throttle = $req->val[2];
		$connectionSeed = $req->val[3];
		$wasStarted = ($req->val[4] != 0);
		$wasOpen = ($req->val[5] != 0);
		$addition = self::buildReplacementAddition(
			$connectionSeed, $throttle, $ratioViews, self::STE_UPDATED, $marker
		);

		// Stage stopped: a failed pre-commit replacement cannot write shared data.
		$loadedHash = rTorrent::sendTorrent($torrent, false, false, $baseDir,
			$label, $saveUploadedTorrents, false, true, $addition);
		$owner = self::waitForLoad($newHash, $marker);
		if(!$loadedHash || strcasecmp((string) $loadedHash, (string) $newHash) !== 0 || $owner !== 'ours')
		{
			// Restore the old torrent even when the staged status is unknown:
			// d.start on it is safe to repeat and is the only recovery there is.
			if($owner === 'ours')
				self::eraseStaged($newHash);
			self::restoreExistingTorrent($hash, $wasOpen, $wasStarted);
			return self::STE_ERROR;
		}

		// Commit point: erase the old torrent.
		$eraseReq = new rXMLRPCRequest( new rXMLRPCCommand("d.erase", $hash ) );
		$eraseReq->important = false;
		if(!$eraseReq->success())
		{
			$oldExists = self::torrentExists($hash);
			if($oldExists === true)
			{
				self::eraseStaged($newHash);
				self::restoreExistingTorrent($hash, $wasOpen, $wasStarted);
				return self::STE_ERROR;
			}
			if($oldExists === null)
			{
				// Both fates are unknowable: keep the marked staged copy so a
				// later run can adopt it, and touch nothing else.
				return self::STE_ERROR;
			}
			// The old torrent is gone despite the failed erase: proceed.
		}

		self::activateReplacement($newHash, $wasOpen, $wasStarted);
		self::cleanupObsoleteFiles($oldTorrent, $torrent, $baseDir);
		self::clearReplacementMarker($newHash);
		return null;
	}

	static private function appendAnnounceUrls($value, &$urls)
	{
		if(is_array($value))
		{
			foreach($value as $item)
				self::appendAnnounceUrls($item, $urls);
		}
		elseif(is_string($value) && $value !== '' && !in_array($value, $urls, true))
			$urls[] = $value;
	}

	static public function run_ex($hash, $fname){
		$torrent = new Torrent( $fname );
		if(!$torrent->errors()){
			$comment = (string) $torrent->comment();

			foreach (self::$TRACKERS as $commentFilter => $tracker)
				if(preg_match($commentFilter, $comment))
					return call_user_func($tracker['handler'], $comment, $hash, $torrent);

			$announces = array();
			self::appendAnnounceUrls($torrent->announce(), $announces);
			self::appendAnnounceUrls($torrent->announce_list(), $announces);

			// Announce matching is a fallback only after every comment handler had
			// a chance to claim the topic URL.
			foreach (self::$TRACKERS as $tracker)
			{
				foreach($announces as $announce)
					if(preg_match($tracker['announceFilter'], $announce))
						return call_user_func($tracker['handler'], $announce, $hash, $torrent);
			}
		}
		return self::STE_NOT_NEED;
	}

	static public function logDebug($message)
	{
		global $rutrackerCheckDebug;
		if(!empty($rutrackerCheckDebug))
			FileUtil::toLog('rutracker_check: ' . preg_replace('/[\r\n]+/', ' ', (string) $message));
	}

	static public function makeClient( $url, $method="GET", $content_type="", $body="" )
	{
		$client = new Snoopy();
		$client->read_timeout = 5;
		$client->_fp_timeout  = 5;

		// Pretend to be a modern browser to reduce 403/anti-bot errors
		$client->agent = self::USER_AGENT;

		@$client->fetchComplex($url, $method, $content_type, $body);

		// Socket errors are negative; the https path stores curl's exit code,
		// which is below any real HTTP status.
		if($client->status < 100)
		{
			$host = @parse_url($url, PHP_URL_HOST);
			self::logDebug("Snoopy fetch failed: host=".(is_string($host) ? $host : 'unknown')." status=".$client->status);
		}

		return $client;
	}

	static public function run( $hash, $state = null, $time = null, $successful_time = null, $label = null )
	{
		global $ignoreLabels;

		// update.php can pass cached state directly, bypassing getState(). Check
		// the live target before acting on a possibly stale scheduler row.
		if(!is_null($state))
		{
			$exists = self::torrentExists($hash);
			if($exists === false)
				return(true);
			if($exists === null)
				return(false);
		}

		if(is_null($state) && !self::getState( $hash, $state, $time, $successful_time, $label ) && ($state == self::STE_NOT_NEED))
			return(true);

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
			$stateWrite = self::setState( $hash, $state );
			if($stateWrite === null) return(true);
			if(!$stateWrite) return(false);

			$fname = rTorrentSettings::get()->session.$hash.".torrent";

			if(is_readable($fname))	$state = self::run_ex($hash, $fname);
			if($state==self::STE_INPROGRESS) $state=self::STE_ERROR;

			if(!is_null($state)) self::setState( $hash, $state );
		}
		return($state != self::STE_CANT_REACH_TRACKER);
	}

}
