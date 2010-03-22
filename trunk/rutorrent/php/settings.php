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
	public $gid = array();
	public $uid = -1;
	public $iVersion = null;
	public $version;
	public $libVersion;
	public $plugins = array();
	public $mostOfMethodsRenamed = false;

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
				if($this->iVersion>0x806)
					$this->mostOfMethodsRenamed = true;
				else
				if($this->iVersion==0x806)
				{
					$req = new rXMLRPCRequest( new rXMLRPCCommand("get_safe_sync") );
					$req->important = false;
					if($req->run() && $req->fault)
						$this->mostOfMethodsRenamed = true;
				}
				if(is_dir($this->session) && isLocalMode())
				{
					$randName = uniqid("/tmp/rutorrent-stats-");
					$req = new rXMLRPCRequest(
        					new rXMLRPCCommand("execute",array("sh","-c","id -u > ".$randName." ; id -G >> ".$randName." ; echo ~ >> ".$randName)));
					if($req->run() && !$req->fault && (($line=file($randName))!==false) && (count($line)>2))
					{
						$this->uid = intval(trim($line[0]));
						$this->gid = explode(' ',trim($line[1]));
						if(!empty($this->directory) &&
							($this->directory[0]=='~'))
							$this->directory = trim($line[2]).substr($this->directory,1);	
						$req = new rXMLRPCRequest(new rXMLRPCCommand( "execute", array("rm",$randName) ));
						$req->run();
					}
				}
				$this->store();
			}
		}
	}
	public function getEventCommand($cmd1,$cmd2,$args)
	{
		if($this->iVersion<0x804)
			$cmd = new rXMLRPCCommand($cmd1);
		else
		if($this->mostOfMethodsRenamed)
			$cmd = new rXMLRPCCommand('method.set_key','event.download.'.$cmd2);
		else
			$cmd = new rXMLRPCCommand('system.method.set_key','event.download.'.$cmd2);
		$cmd->addParameters($args);
		return($cmd);
	}
	public function getOnInsertCommand($args)
	{
		return($this->getEventCommand('on_insert','inserted_new',$args));
	}
	public function getOnEraseCommand($args)
	{
		return($this->getEventCommand('on_erase','erased',$args));
	}
	public function getOnFinishedCommand($args)
	{
	        return($this->getEventCommand('on_finished','finished',$args));
	}
	public function getOnResumedCommand($args)
	{
	        return($this->getEventCommand('on_start','resumed',$args));
	}
}

?>
