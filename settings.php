<?php

$rootPath = "./";
if(!is_file('util.php'))
	$rootPath = "../../";
require_once( $rootPath."util.php" );
require_once( $rootPath."xmlrpc.php" );

class rTorrentSettings
{
	public $hash = "rtorrent.dat";

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

	static public function load()
	{
		global $settings;
		global $rootPath;
		$cache = new rCache( $rootPath.$settings );
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
		global $rootPath;
		$cache = new rCache( $rootPath.$settings );
		return($cache->set($this));
	}
	public function obtain()
	{
		$this->path=realpath(dirname('.'));
		$len = strlen($this->path);
		if(($len>0) && ($this->path[$len-1]!='/'))
			$this->path.='/';
		$req = new rXMLRPCRequest( new rXMLRPCCommand("to_kb", 1024) );
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
					if($ss)
					{
				        	$this->gid = $ss['gid'];
					        $this->uid = $ss['uid'];
					}
				}
				$this->store();
			}
		}
	}
}

?>
