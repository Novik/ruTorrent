<?php

rTorrentSettings::$rootPath = "./";
if(!is_file('util.php'))
	rTorrentSettings::$rootPath = "../../";
require_once( rTorrentSettings::$rootPath."util.php" );
require_once( rTorrentSettings::$rootPath."xmlrpc.php" );

class rTorrentSettings
{
	public $hash = "rtorrent.dat";
	static public $rootPath;

	public $linkExist = false;
	public $badXMLRPCVersion = true;
	public $directory = null;
	public $session = null;
	public $path = null;
	public $gid = -1;
	public $uid = -1;
	public $iVersion = null;
	public $version;
	public $plugins = array();
	public $mygid = -1;
	public $myuid = -1;

	static public function load()
	{
		global $settings;
		$cache = new rCache( self::$rootPath.$settings );
		$rts = new rTorrentSettings();
		$cache->get($rts);
		return($rts);
	}
	public function registerPlugin($plugin,$data = true)
	{
		$this->plugins[$plugin] = $data;
	}
	public function getPluginData($plugin)
	{
		$ret = null;
		if(array_key_exists($plugin,$this->plugins))
			$ret = $this->plugins[$plugin];
		return($ret);
	}
	public function isPluginRegistered($plugin)
	{
		return(array_key_exists($plugin,$this->plugins));
	}
	public function store()
	{
		global $settings;
		$cache = new rCache( self::$rootPath.$settings );
		return($cache->set($this));
	}
	public function obtain()
	{
		if(function_exists('posix_getuid') && function_exists('posix_getgid'))
		{
			$this->myuid = posix_getuid();
			$this->mygid = posix_getgid();
		}
		else
		{
			$randName = '/tmp/rutorrent-'.rand().'.tmp';
			@file_put_contents($randName, '');
			$ss=@stat($randName);
			if($ss)
			{
		        	$this->mygid = $ss['gid'];
			        $this->myuid = $ss['uid'];
			        @unlink($randName);
			}
		}
		$this->path=realpath(dirname('.'));
		$len = strlen($this->path);
		if(($len>0) && ($this->path[$len-1]!='/'))
			$this->path.='/';
		$req = new rXMLRPCRequest( new rXMLRPCCommand("to_kb", floatval(1024)) );
		if($req->run())
		{
			$this->linkExist = true;
			if(!$req->fault)
				$this->badXMLRPCVersion = false;
			$req = new rXMLRPCRequest( array(
				new rXMLRPCCommand("get_directory"),
				new rXMLRPCCommand("get_session"),
				new rXMLRPCCommand("system.client_version")
				) );
			if($req->run() && !$req->fault)
			{
				$this->directory = $req->strings[0];
  		                $this->session = $req->strings[1];
				$this->version = $req->strings[2];
				$parts = explode('.', $this->version);
				$this->iVersion = 0;
				for($i = 0; $i<count($parts); $i++)
					$this->iVersion = ($this->iVersion<<8) + $parts[$i];
				if(is_dir($this->session))
				{
					$ss=@stat($this->session.'rtorrent.lock');
					if(!$ss)
						$ss=@stat($this->session.'rtorrent.dht_cache');
					if(!$ss)
						$ss=@stat($this->session);
					if($ss)
					{
				        	$this->gid = $ss['gid'];
					        $this->uid = $ss['uid'];
						if(!empty($this->directory) &&
							($this->directory[0]=='~'))
						{
							if(function_exists('posix_getpwuid'))
							{
					        		$ui = posix_getpwuid($this->uid);
						        	$this->directory = $ui["dir"].substr($this->directory,1);
			                		}
			                		else
			                		{
			                		 	$req = new rXMLRPCRequest( new rXMLRPCCommand("execute_capture", array("echo","~")) );
			                		 	if($req->run() && !$req->fault)
				                		 	$this->directory = trim($req->strings[0]).substr($this->directory,1);
			                		}
			                	}
					}
				}
				$this->store();
			}
		}
	}
}

?>
