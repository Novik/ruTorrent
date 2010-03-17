<?php

require_once( dirname(__FILE__).'/xmlrpc.php' );
require_once( $rootPath.'/php/cache.php');

class rTorrentSettings
{
	public $hash = "rtorrent.dat";
	public $linkExist = false;
	public $badXMLRPCVersion = true;
	public $directory = '/tmp';
	public $session = null;
	public $gid = -1;
	public $uid = -1;
	public $iVersion = null;
	public $version;
	public $libVersion;
	public $plugins = array();
	public $mygid = -1;
	public $myuid = -1;

	static public function load()
	{
		$cache = new rCache();
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
		$cache = new rCache();
		return($cache->set($this));
	}
	public function obtain()
	{
		if(function_exists('posix_geteuid') && function_exists('posix_getegid'))
		{
			$this->myuid = posix_geteuid();
			$this->mygid = posix_getegid();
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
		$req = new rXMLRPCRequest( new rXMLRPCCommand("to_kb", floatval(1024)) );
		if($req->run())
		{
			$this->linkExist = true;
			if(!$req->fault)
				$this->badXMLRPCVersion = false;
			$req = new rXMLRPCRequest( array(
				new rXMLRPCCommand("get_directory"),
				new rXMLRPCCommand("get_session"),
				new rXMLRPCCommand("system.client_version"),
				new rXMLRPCCommand("system.library_version"),
				new rXMLRPCCommand("set_xmlrpc_size_limit",67108863)
				) );
			if($req->run() && !$req->fault && (count($req->val)>3))
			{
				$this->directory = $req->val[0];
  		                $this->session = $req->val[1];
				$this->version = $req->val[2];
				$this->libVersion = $req->val[3];
				$parts = explode('.', $this->version);
				$this->iVersion = 0;
				for($i = 0; $i<count($parts); $i++)
					$this->iVersion = ($this->iVersion<<8) + $parts[$i];
				if(is_dir($this->session) && isLocalMode())
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
				                		 	$this->directory = trim($req->val[0]).substr($this->directory,1);
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
