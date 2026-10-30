<?php
require_once( dirname(__FILE__)."/../../php/xmlrpc.php" );
require_once( dirname(__FILE__)."/../../php/rtorrent.php" );
require_once( dirname(__FILE__)."/../../php/cache.php");
require_once( dirname(__FILE__)."/../../php/settings.php");
require_once( dirname(__FILE__).'/../_task/task.php' );
eval( FileUtil::getPluginConf( 'unpack' ) );

class rUnpack
{
	public $hash = "unpack.dat";
	public $modified = false;
	public $enabled = 0;
	public $filter = '/.*/';
	public $path = "";
	public $addLabel = 0;
	public $addName = 0;

	static public function load()
	{
		$cache = new rCache();
		$up = new rUnpack();
		$cache->get( $up );
		return($up);
	}
	public function store()
	{
		$cache = new rCache();
		return($cache->set( $this ));
	}
	public function set()
	{
		if( !isset( $HTTP_RAW_POST_DATA ) )
			$HTTP_RAW_POST_DATA = file_get_contents( "php://input" );
		if( isset( $HTTP_RAW_POST_DATA ) )
		{
			$vars = explode( '&', $HTTP_RAW_POST_DATA );
			$this->enabled = 0;
			$this->path = "";
			foreach( $vars as $var )
			{
				$parts = explode( "=", $var );
				if( $parts[0] == "unpack_enabled" )
					$this->enabled = $parts[1];
				else
				if( $parts[0] == "unpack_label" )
					$this->addLabel = $parts[1];
				else
				if( $parts[0] == "unpack_name" )
					$this->addName = $parts[1];
				else
				if( $parts[0] == "unpack_filter" )
				{
					$this->filter = trim(rawurldecode($parts[1]));
					if(@preg_match($this->filter, null) === false)
						$this->filter = "/.*/";
				}
				else
				if( $parts[0] == "unpack_path" )
				{
					$this->path = trim(rawurldecode($parts[1]));
					if(($this->path != '') && !rTorrentSettings::get()->correctDirectory($this->path))
						$this->path = '';
				}
			}
		}
		$this->store();
		$this->setHandlers();
	}
	public function get()
	{
		return("theWebUI.unpackData = { enabled: ".$this->enabled.", path : '".addslashes( $this->path ).
			"', filter : '".addslashes( $this->filter )."', addLabel: ".$this->addLabel.", addName: ".$this->addName." };\n");
	}

	protected static function log( $msg )
	{
		global $unpack_debug_enabled;
		if( $unpack_debug_enabled )
		{
			FileUtil::toLog($msg);
		}
	}

	protected function checkOneFile( $filePath, &$filesToDelete )
	{
		if (is_link($filePath))
		{
			self::log("Unpack: SoftLink operation enabled. Deleting " . $filePath);
			$filesToDelete .= $filePath . ";";
		}
		else
		{
			$stat = LFS::stat($filePath);
			if($stat)
			{
				if($stat['nlink'] > 1)
				{
					self::log("Unpack: HardLink operation enabled. Deleting " . $filePath);
					$filesToDelete .= $filePath . ";";
			    	}
				else
				{
					self::log("Unpack: Copy operation enabled. Deleting " . $filePath);
				    	$filesToDelete .= $filePath . ";";
				}
			}
		}
	}

	protected function processAutoDelete( $basename, $downloadname, $regex, &$filesToDelete )
	{
		global $deleteAutoArchives;
		if($deleteAutoArchives)
		{
			if($downloadname === $basename)
			{
				self::log("Unpack: No move operation enabled. Not deleting files.");
			}
			else if (!file_exists($downloadname))
			{
				self::log("Unpack: Move operation enabled. Not deleting files.");
			}
			else
			{
				if($regex)
				{
					foreach($regex as $fileName)
					{
						$filePath = $fileName->getPathname();
						$this->checkOneFile( $filePath, $filesToDelete );
					}
				}
				else
				{
					$this->checkOneFile( $basename, $filesToDelete );
				}
			}
		}
	}

	// A refusal is logged whatever $unpack_debug_enabled says, since an
	// automatic unpack has no one to answer.
	protected static function logRefusal( $how, $hash, $reason, $entries )
	{
		// A manual unpack's hash comes from the request: only a well-formed
		// one is copied into the log.
		$hash = preg_match('/^[0-9A-Fa-f]{40}$/D', $hash) ? strtoupper($hash) : "invalid";
		FileUtil::toLog("unpack: ".$how." unpack refused hash=".$hash." reason=".$reason." entries=".$entries);
	}

	// The unpack scripts' arguments 7-10, for place.php (see stage.sh).
	protected static function placeArguments( $how, $hash )
	{
		return(" ".Utility::escapeShellArg(Utility::getPHP())." ".Utility::escapeShellArg($how)." ".
			Utility::escapeShellArg($hash)." ".Utility::escapeShellArg(User::getUser()));
	}

	// What place() calls: place.php refuses to start when one is disabled,
	// which PHP 7.4 would answer with null, "not a link", and PHP 8 with an
	// Error half way through.
	const PLACEMENT_FUNCTIONS = array('scandir', 'lstat', 'is_link', 'is_dir', 'file_exists', 'unlink', 'rename',
		'mkdir', 'filemtime', 'touch');

	// Moves what one extractor run wrote into its private directory $stage
	// into $output (place.php, run by the unpack scripts). Every symbolic
	// link the archive brought is dropped first, even one that resolves
	// inside for now, since a directory is then moved whole; $skipped more
	// symbolic links were left out by the extractor itself (unrar -ol-) and
	// count the same. Nothing is written into or through a symbolic link in
	// $output. Each refusal is named on stderr, the task's output, and
	// counted by reason in the log. Returns the exit status.
	public static function place( $how, $hash, $stage, $output, $skipped )
	{
		$refused = ($skipped > 0) ? array("archive-symbolic-link"=>$skipped) : array();
		if(self::dropLinks($stage, '', $refused))
			self::placeEntries($stage, $output, '', $refused);
		foreach($refused as $reason=>$entries)
			self::logRefusal($how, $hash, $reason, $entries);
		return(empty($refused) ? 0 : 1);
	}

	// A refusal the unpack scripts meet outside a placement: named on stderr
	// and logged.
	public static function report( $how, $hash, $reason, $name )
	{
		self::tellRefusal($reason, $name);
		self::logRefusal($how, $hash, $reason, 1);
	}

	// Names a refusal on stderr, the task's output.
	protected static function tellRefusal( $reason, $name )
	{
		fwrite(STDERR, "unpack: refused ".$reason.": ".addcslashes($name, "\0..\37\177\\")."\n");
	}

	protected static function refusePlacement( &$refused, $reason, $name )
	{
		$refused[$reason] = (isset($refused[$reason]) ? $refused[$reason] : 0) + 1;
		self::tellRefusal($reason, $name);
	}

	// The names in $directory, or null after a refusal.
	protected static function placementNames( $directory, $shown, &$refused )
	{
		$names = @scandir($directory);
		if($names === false)
		{
			self::refusePlacement($refused, "move-failed", ($shown === '') ? '.' : $shown);
			return(null);
		}
		return(array_diff($names, array('.', '..')));
	}

	// Removes every symbolic link under $directory; false if one stays or
	// an entry cannot be told a link or not.
	protected static function dropLinks( $directory, $shown, &$refused )
	{
		$names = self::placementNames($directory, $shown, $refused);
		if($names === null)
			return(false);
		$dropped = true;
		foreach($names as $name)
		{
			$path = $directory.'/'.$name;
			$stat = @lstat($path);
			if($stat === false)
			{
				self::refusePlacement($refused, "move-failed", $shown.$name);
				$dropped = false;
			}
			else
			if(($stat['mode'] & 0170000) === 0120000)
			{
				self::refusePlacement($refused, "archive-symbolic-link", $shown.$name);
				$dropped = @unlink($path) && $dropped;
			}
			else
			if(($stat['mode'] & 0170000) === 0040000)
				$dropped = self::dropLinks($path, $shown.$name.'/', $refused) && $dropped;
		}
		return($dropped);
	}

	protected static function placeEntries( $from, $to, $shown, &$refused )
	{
		$names = self::placementNames($from, $shown, $refused);
		if($names === null)
			return;
		foreach($names as $name)
		{
			$source = $from.'/'.$name;
			$target = $to.'/'.$name;
			$entry = $shown.$name;
			// A copy to another filesystem gets the time of the copy.
			$mtime = @filemtime($source);
			if(!is_dir($source))
			{
				if(is_dir($target) && !is_link($target))
					self::refusePlacement($refused, "output-type-conflict", $entry);
				else
				// unlink() first: rename() to another filesystem copies, and
				// the copy would write into the file or through the link there.
				if(((is_link($target) || file_exists($target)) && !@unlink($target)) || !@rename($source, $target))
					self::refusePlacement($refused, "move-failed", $entry);
				else
					@touch($target, $mtime);
			}
			else
			if(is_link($target))
				self::refusePlacement($refused, "output-symbolic-link", $entry);
			else
			if(is_dir($target))
				self::placeEntries($source, $target, $entry.'/', $refused);
			else
			if(file_exists($target))
				self::refusePlacement($refused, "output-type-conflict", $entry);
			else
			// rename() does not move a directory to another filesystem.
			if(!@rename($source, $target))
			{
				if(@mkdir($target))
				{
					self::placeEntries($source, $target, $entry.'/', $refused);
					@touch($target, $mtime);
				}
				else
					self::refusePlacement($refused, "move-failed", $entry);
			}
		}
	}

	protected static function isDryRun()
	{
		global $unpack_debug_enabled;
		return( $unpack_debug_enabled === 'dry-run' );
	}

	protected static function processTask( $task, $commands )
	{
		self::log("Start unpack operation as task ".$task->id." with arguments ".
			json_encode($commands,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
		return( self::isDryRun() ? array
		(
			"no"=>$task->id,
			"pid"=>0,
			"status"=>255,
			"log"=>array(),
			"params"=>array(),
			"errors"=>array( "Dry-run operation, no real task start" )
		) : $task->start($commands, 0) );
	}

	// quotaspace is a third-party plugin: ruTorrent does not ship rquota.php, so
	// a registration is not a promise that the file is there or that it declares
	// rQuota. A quota that cannot be consulted is not a quota that was exceeded,
	// so the unpack proceeds, as it does where the plugin was never added.
	// class_exists() is asked not to autoload: rQuota is the plugin file's to
	// declare, and the core autoloader would only go looking for it under
	// php/utility/.
	static protected function quotaAllows()
	{
		if(!rTorrentSettings::get()->isPluginRegistered('quotaspace'))
			return(true);
		$rquota = dirname(__FILE__)."/../quotaspace/rquota.php";
		if(!is_readable($rquota))
			return(true);
		require_once( $rquota );
		if(!class_exists('rQuota',false))
			return(true);
		$qt = rQuota::load();
		return( $qt->check() ? true : false );
	}

	public function startSilentTask($basename,$downloadname,$label,$name,$hash)
	{
		global $rootPath;
		global $cleanupAutoTasks;
		global $unpackToTemp;

		if(!self::quotaAllows())
			return;

		$pathToUnrar = Utility::getExternal("unrar");
		$pathToUnzip = Utility::getExternal("unzip");
		$zipPresent = false;
		$rarPresent = false;
		$outPath = $this->path;

		if(($outPath!='') && !rTorrentSettings::get()->correctDirectory($outPath))
			$outPath = '';

		self::log("[Auto] Check torrent [$name] at [$basename] for archives");

		if(is_dir($basename))
		{
			$postfix = "_dir";
			if($outPath=='')
				$outPath = $basename;
			$basename = FileUtil::addslash($basename);

			$filesToDelete = "";
			$downloadname = FileUtil::addslash($downloadname);
			$Directory = new RecursiveDirectoryIterator($basename);
			$Iterator = new RecursiveIteratorIterator($Directory);
			$rarRegex = new RegexIterator($Iterator, '/.*\.(rar|r\d\d|\d\d\d)$/si');
			$zipRegex = new RegexIterator($Iterator, '/.*\.zip$/si');

			if(USE_UNRAR && (sizeof(iterator_to_array($rarRegex)) > 0))
			{
				$rarPresent = true;
				self::log("[Auto] Found RAR archive(s)");
				$this->processAutoDelete( $basename, $downloadname, $rarRegex, $filesToDelete );
			}
			if(USE_UNZIP && (sizeof(iterator_to_array($zipRegex)) > 0))
			{
				$zipPresent = true;
				self::log("[Auto] Found ZIP archive(s)");
				$this->processAutoDelete( $basename, $downloadname, $zipRegex, $filesToDelete );
			}
			$mode = (($rarPresent && $zipPresent) ? "all" : ($zipPresent ? "zip" : ($rarPresent ? "rar" : null)));
		}
		else
		{
			$postfix = "_file";
			if(USE_UNRAR && (preg_match("'.*\.(rar|r\d\d|\d\d\d)$'si", $basename)==1))
			{
				$rarPresent = true;
				self::log("[Auto] This is a RAR archive");
				$this->processAutoDelete( $basename, $downloadname, NULL, $filesToDelete );
			}
			else
			if(USE_UNZIP && (preg_match("'.*\.zip$'si", $basename)==1))
			{
				$zipPresent = true;
				self::log("[Auto] This is a ZIP archive");
				$this->processAutoDelete( $basename, $downloadname, NULL, $filesToDelete );
			}
			if($outPath=='')
				$outPath = dirname($basename);
			$mode = ($zipPresent ? 'zip' : ($rarPresent ? 'rar' : null));
		}
		if($mode)
		{
			$arh = (($mode == "zip") ? $pathToUnzip : $pathToUnrar);
			$outPath = FileUtil::addslash($outPath);
        		if($this->addLabel && ($label!=''))
        			$outPath.=FileUtil::addslash($label);
	        	if($this->addName && ($name!=''))
				$outPath.=FileUtil::addslash($name);
			if(!rTorrent::isInsideTopDirectory($outPath))
			{
				self::log("[Auto] Not unpacked: [$outPath] is outside the top directory");
				return;
			}
			if($unpackToTemp)
			{
				$randTempDirectory = FileUtil::addslash( FileUtil::getTempFilename('unpack') );
				self::log("Unpack: Unpack to temp enabled. Unpacking to " . $randTempDirectory);
			}
			else
			{
				$randTempDirectory = "";
			}
	        	$commands[] = escapeshellarg($rootPath.'/plugins/unpack/un'.$mode.$postfix.'.sh')." ".
				escapeshellarg($arh)." ".
				escapeshellarg($basename)." ".
				escapeshellarg($outPath)." ".
				escapeshellarg($pathToUnzip)." ".
				escapeshellarg($filesToDelete)." ".
				escapeshellarg($randTempDirectory).
				self::placeArguments("automatic", $hash);
			if($cleanupAutoTasks)
				$commands[] = 'rm -r "${dir}"';

			self::log("[Auto] Unpack files from torrent [$name] at [$basename] to [$outPath]");

			$task = new rTask( array
			(
				'arg' => FileUtil::getFileName(FileUtil::delslash($basename)),
				'requester'=>'unpack',
				'name'=>'unpack',
				'hash'=>$hash,
				'dir'=>$outPath,
				'mode'=>null,
				'no'=>null
			) );
			self::processTask( $task, $commands );
		}
		else
		{
			self::log("[Auto] Archives not found");
		}
	}

	public function startTask( $hash, $outPath, $mode, $fileno )
	{
		global $rootPath;
		$ret = array( "no"=>-1, "pid"=>0, "status"=>255, "log"=>array(), "errors"=>array("Unknown error.") );

		if(!self::quotaAllows())
		{
			$ret["errors"] = array("Quota limitation was reached. Unpack failed.");
			return($ret);
		}

		$taskArgs = array
		(
			'requester'=>'unpack',
			'name'=>'unpack',
			'hash'=>$hash,
			'dir'=>$outPath,
			'mode'=>$mode,
			'no'=>$fileno
		);
		if(($outPath!='') && !rTorrentSettings::get()->correctDirectory($outPath))
			$outPath = '';

		if(!empty($mode))
	        {
			$req = new rXMLRPCRequest(
				new rXMLRPCCommand( "f.get_frozen_path", array($hash,intval($fileno)) ));
			if($req->success())
			{
				$filename = $req->val[0];
				if($filename=='')
				{
					$req = new rXMLRPCRequest( array(
						new rXMLRPCCommand( "d.open", $hash ),
						new rXMLRPCCommand( "f.get_frozen_path", array($hash,intval($fileno)) ),
						new rXMLRPCCommand( "d.close", $hash ) ) );
					if($req->success())
						$filename = $req->val[1];
				}

				if($outPath=='')
					$outPath = dirname($filename);
				if(!rTorrent::isInsideTopDirectory($outPath))
				{
					self::log("[Manual] Not unpacked: [$outPath] is outside the top directory");
					$ret["errors"] = array("The directory is outside the top directory. Unpack failed.");
					return($ret);
				}

				$commands = array();
				$arh = Utility::getExternal( ($mode == "zip") ? 'unzip' : 'unrar' );
				$commands[] = escapeshellarg($rootPath.'/plugins/unpack/un'.$mode.'_file.sh')." ".
					escapeshellarg($arh)." ".
					escapeshellarg($filename)." ".
					escapeshellarg(FileUtil::addslash($outPath))." '' '' ''".
					self::placeArguments("manual", $hash);
				$taskArgs['arg'] = FileUtil::getFileName($filename);

				self::log("[Manual] Unpack file [$filename] from torrent [$hash] to [$outPath]");

				$task = new rTask( $taskArgs );
				$ret = self::processTask( $task, $commands );
			}
		}
		else
		{
			$req = new rXMLRPCRequest( array(
				new rXMLRPCCommand( "d.get_base_path", $hash ),
				new rXMLRPCCommand( "d.get_custom1", $hash ),
				new rXMLRPCCommand( "d.get_name", $hash ) )
				);
			if($req->success())
			{
				$basename = $req->val[0];
				$label = rawurldecode($req->val[1]);
				$tname = $req->val[2];
				if($basename=='')
				{
					$req = new rXMLRPCRequest( array(
						new rXMLRPCCommand( "d.open", $hash ),
						new rXMLRPCCommand( "d.get_base_path", $hash ),
						new rXMLRPCCommand( "d.close", $hash ) ) );
					if($req->success())
						$basename = $req->val[1];
				}
				self::log("[Manual] Check torrent [$tname] at [$basename] for archives");
				$req = new rXMLRPCRequest(
					new rXMLRPCCommand( "f.multicall", array($hash,"",getCmd("f.get_path=")) ));
				if($req->success())
				{
				        $rarPresent = false;
				        $zipPresent = false;
					foreach($req->val as $no=>$name)
					{
						if(USE_UNRAR && (preg_match("'.*\.(rar|r\d\d|\d\d\d)$'si", $name)==1))
						{
							self::log("[Manual] Found RAR archive [$name]");
							$rarPresent = true;
						}
						else
						if(USE_UNZIP && (preg_match("'.*\.zip$'si", $name)==1))
						{
							self::log("[Manual] Found ZIP archive [$name]");
							$zipPresent = true;
						}
					}
					$mode = ($rarPresent && $zipPresent) ? 'all' : ($rarPresent ? 'rar' : ($zipPresent ? 'zip' : null));
					if($mode)
					{
						$pathToUnrar = Utility::getExternal("unrar");
						$pathToUnzip = Utility::getExternal("unzip");
						$arh = (($mode == "zip") ? $pathToUnzip : $pathToUnrar);
						if(is_dir($basename))
						{
							$postfix = "_dir";
							if($outPath=='')
								$outPath = $basename;
							$basename = FileUtil::addslash($basename);
						}
						else
						{
							$postfix = "_file";
							if($outPath=='')
								$outPath = dirname($basename);
		        				$pathToUnzip = "";
						}
						$outPath = FileUtil::addslash($outPath);
				        	if($this->addLabel && ($label!=''))
				        		$outPath.=FileUtil::addslash($label);
				        	if($this->addName && ($tname!=''))
				        		$outPath.=FileUtil::addslash($tname);
						if(!rTorrent::isInsideTopDirectory($outPath))
						{
							self::log("[Manual] Not unpacked: [$outPath] is outside the top directory");
							$ret["errors"] = array("The directory is outside the top directory. Unpack failed.");
							return($ret);
						}

				        	$commands[] = escapeshellarg($rootPath.'/plugins/unpack/un'.$mode.$postfix.'.sh')." ".
							escapeshellarg($arh)." ".
							escapeshellarg($basename)." ".
							escapeshellarg($outPath)." ".
							escapeshellarg($pathToUnzip)." '' ''".
							self::placeArguments("manual", $hash);
						$taskArgs['arg'] = FileUtil::getFileName(FileUtil::delslash($basename));

						self::log("[Manual] Unpack files from torrent [$tname] at [$basename] to [$outPath]");

						$task = new rTask( $taskArgs );
						$ret = self::processTask( $task, $commands );
					}
					else
					{
						self::log("[Manual] Archives not found");
					}
				}
			}
		}
		return($ret);
	}

	public function setHandlers()
	{
		global $rootPath;
		if($this->enabled)
		{
			$cmd =  rTorrentSettings::get()->getOnFinishedCommand( array('unpack'.User::getUser(),
					getCmd('execute.nothrow').'={sh,-c,\"$0\" \"$@\" </dev/null >/dev/null 2>&1 &,'.Utility::getPHP().','.$rootPath.'/plugins/unpack/update.php,$'.getCmd('d.get_directory').'=,$'.getCmd('d.get_base_filename').'=,$'.getCmd('d.is_multi_file').
					'=,$'.getCmd('d.get_custom1').'=,$'.getCmd('d.get_name').'=,$'.getCmd('d.get_hash').'=,$'.getCmd('d.get_custom').'=x-dest,'.User::getUser().'}'));
		}
		else
			$cmd = rTorrentSettings::get()->getOnFinishedCommand(array('unpack'.User::getUser(), getCmd('cat=')));
		$req = new rXMLRPCRequest( $cmd );
		return($req->success());
	}
}
