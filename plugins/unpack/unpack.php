<?php
require_once( dirname(__FILE__)."/../../php/xmlrpc.php" );
require_once( dirname(__FILE__)."/../../php/cache.php");
require_once( dirname(__FILE__)."/../../php/settings.php");
require_once( dirname(__FILE__).'/../_task/task.php' );
eval( getPluginConf( 'unpack' ) );

class rUnpack
{
	public $hash = "unpack.dat";
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
					$this->filter = trim(rawurldecode($parts[1]));
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
	}
	public function get()
	{
		return("theWebUI.unpackData = { enabled: ".$this->enabled.", path : '".addslashes( $this->path ).
			"', filter : '".addslashes( $this->filter )."', addLabel: ".$this->addLabel.", addName: ".$this->addName." };\n");
	}
	public function startSilentTask($basename,$label,$name,$hash)
	{
		global $rootPath;
		global $cleanupAutoTasks;
		if(rTorrentSettings::get()->isPluginRegistered('quotaspace'))
		{
			require_once( dirname(__FILE__)."/../quotaspace/rquota.php" );
			$qt = rQuota::load();
			if(!$qt->check())
				return;
		}

		$pathToUnrar = getExternal("unrar");
		$pathToUnzip = getExternal("unzip");
		$outPath = $this->path;
		
		if(($outPath!='') && !rTorrentSettings::get()->correctDirectory($outPath))	
			$outPath = '';
		
		$arh = $pathToUnrar;
		if(is_dir($basename))
		{
			$postfix = "_dir";
			$mode = ((USE_UNRAR && USE_UNZIP) ? "all" : (USE_UNZIP ? "zip" : "rar"));
			if($outPath=='')
				$outPath = $basename;
			$basename = addslash($basename);
		}
		else
		{
			$postfix = "_file";
			if(USE_UNRAR && (preg_match("'.*\.(rar|r\d\d|\d\d\d)$'si", $basename)==1))
				$rarPresent = true;
			else
			if(USE_UNZIP && (preg_match("'.*\.zip$'si", $basename)==1))
				$zipPresent = true;
			if($outPath=='')
				$outPath = dirname($basename);
			$mode = ($zipPresent ? 'zip' : ($rarPresent ? 'rar' : null));
		        $pathToUnzip = "";
		}
		if($mode)
		{
			$outPath = addslash($outPath);
        		if($this->addLabel && ($label!=''))
        			$outPath.=addslash($label);
	        	if($this->addName && ($name!=''))
				$outPath.=addslash($name);

	        	$commands[] = escapeshellarg($rootPath.'/plugins/unpack/un'.$mode.$postfix.'.sh')." ".
				escapeshellarg($arh)." ".
				escapeshellarg($basename)." ".
				escapeshellarg($outPath)." ".
				escapeshellarg($pathToUnzip);
			if($cleanupAutoTasks)
				$commands[] = 'rm -r "${dir}"';	
			(new rTask( array
			( 
				'arg'=>call_user_func('end',explode('/',delslash($basename))),
				'requester'=>'unpack',
				'name'=>'unpack', 
				'hash'=>$hash, 
				'dir'=>$outPath, 
				'mode'=>null, 
				'no'=>null
			) ))->start($commands, 0);
		}
	}

	public function startTask( $hash, $outPath, $mode, $fileno )
	{
		global $rootPath;
		$ret = array( "no"=>-1, "pid"=>0, "status"=>255, "log"=>array(), "errors"=>array("Unknown error.") );

		if(rTorrentSettings::get()->isPluginRegistered('quotaspace'))
		{
			require_once( dirname(__FILE__)."/../quotaspace/rquota.php" );
			$qt = rQuota::load();
			if(!$qt->check())
			{
				$ret["errors"] = array("Quota limitation was reached. Unpack failed.");
				return($ret);
			}
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

				$commands = array();
				$arh = getExternal( ($mode == "zip") ? 'unzip' : 'unrar' );
				$commands[] = escapeshellarg($rootPath.'/plugins/unpack/un'.$mode.'_file.sh')." ".
					escapeshellarg($arh)." ".
					escapeshellarg($filename)." ".
					escapeshellarg(addslash($outPath));
				$taskArgs['arg'] = call_user_func('end',explode('/',$filename));
				$ret = (new rTask( $taskArgs ))->start($commands, 0);
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
				$req = new rXMLRPCRequest( 
					new rXMLRPCCommand( "f.multicall", array($hash,"",getCmd("f.get_path=")) ));
				if($req->success())
				{
				        $rarPresent = false;
				        $zipPresent = false;
					foreach($req->val as $no=>$name)
					{
						if(USE_UNRAR && (preg_match("'.*\.(rar|r\d\d|\d\d\d)$'si", $name)==1))
							$rarPresent = true;
						else
						if(USE_UNZIP && (preg_match("'.*\.zip$'si", $name)==1))
							$zipPresent = true;
					}
					$mode = ($rarPresent && $zipPresent) ? 'all' : ($rarPresent ? 'rar' : ($zipPresent ? 'zip' : null));
					if($mode)
					{
						$pathToUnrar = getExternal("unrar");
						$pathToUnzip = getExternal("unzip");
						$arh = (($mode == "zip") ? $pathToUnzip : $pathToUnrar);
						if(is_dir($basename))
						{
							$postfix = "_dir";
							if($outPath=='')
								$outPath = $basename;
							$basename = addslash($basename);
						}
						else
						{
							$postfix = "_file";
							if($outPath=='')
								$outPath = dirname($basename);
		        				$pathToUnzip = "";
						}
						$outPath = addslash($outPath);
				        	if($this->addLabel && ($label!=''))
				        		$outPath.=addslash($label);
				        	if($this->addName && ($tname!=''))
				        		$outPath.=addslash($tname);

				        	$commands[] = escapeshellarg($rootPath.'/plugins/unpack/un'.$mode.$postfix.'.sh')." ".
							escapeshellarg($arh)." ".
							escapeshellarg($basename)." ".
							escapeshellarg($outPath)." ".
							escapeshellarg($pathToUnzip);
						$taskArgs['arg'] = call_user_func('end',explode('/',delslash($basename)));
						$ret = (new rTask( $taskArgs ))->start($commands, 0);	
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
			$cmd =  rTorrentSettings::get()->getOnFinishedCommand( array('unpack'.getUser(), 
					getCmd('execute').'={'.getPHP().','.$rootPath.'/plugins/unpack/update.php,$'.getCmd('d.get_directory').'=,$'.getCmd('d.get_base_filename').'=,$'.getCmd('d.is_multi_file').
					'=,$'.getCmd('d.get_custom1').'=,$'.getCmd('d.get_name').'=,'.getCmd('d.get_hash').'=,'.getUser().'}'));
		}
		else
			$cmd = rTorrentSettings::get()->getOnFinishedCommand(array('unpack'.getUser(), getCmd('cat=')));
		$req = new rXMLRPCRequest( $cmd );
		return($req->success());
	}
}
