<?php

require_once( 'xmlrpc.php' );
require_once( 'cache.php');

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
	public $hooks = array();
	public $mostOfMethodsRenamed = false;
	public $aliases = array();
	public $started = 0;
	public $server = '';
	public $portRange = '6890-6999';
	public $idNotFound = false;

	static private $theSettings = null;

	private function __construct( )
    	{
	}

	private function __clone()
    	{
    	}

	public function registerPlugin($plugin,$data = true)
	{
		$this->plugins[$plugin] = $data;
	}
	public function unregisterPlugin($plugin)
	{
		unset($this->plugins[$plugin]);
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

	public function registerEventHook( $plugin, $ename )
	{
		if(is_array($ename))
			foreach( $ename as $name )
				$this->hooks[$name][] = $plugin;
		else
			$this->hooks[$ename][] = $plugin;
	}
	public function unregisterEventHook( $plugin, $ename )
	{
		for( $i = 0; $i<count($this->hooks[$ename]); $i++ )
		{
			if($this->hooks[$ename][$i] == $plugin)
			{
				unset($this->hooks[$ename][$i]);
				if( count($this->hooks[$ename])==0 )
					unset($this->hooks[$ename]);
				break;
			}
		}
	}
	public function pushEvent( $ename, $prm )
	{
		if( array_key_exists($ename,$this->hooks))
			for( $i = 0; $i<count($this->hooks[$ename]); $i++ )
			{
				$pname = $this->hooks[$ename][$i];
				$file = dirname(__FILE__).'/../plugins/'.$pname.'/hooks.php';
				if(is_file($file))
				{
					require_once( $file );
					$func = $pname.'Hooks::On'.$ename;
					if(is_callable( $func ) && 
						(call_user_func_array($func,array($prm))==true))
						break;
				}
			}
	}

	public function store()
	{
		$cache = new rCache();
		return($cache->set($this));
	}
	static public function get( $create = false )
	{
		if(is_null(self::$theSettings))
		{
			self::$theSettings = new rTorrentSettings();
			if($create)
				self::$theSettings->obtain();
			else
			{
				$cache = new rCache();
				$cache->get(self::$theSettings);
			}
		}
		return(self::$theSettings);
	}
	public function obtain()
	{
		$req = new rXMLRPCRequest( new rXMLRPCCommand("system.client_version") );
		if($req->run() && count($req->val))
		{
			$this->linkExist = true;
			$this->version = $req->val[0];
			$parts = explode('.', $this->version);
			$this->iVersion = 0;
			for($i = 0; $i<count($parts); $i++)
				$this->iVersion = ($this->iVersion<<8) + $parts[$i];
                        $req = new rXMLRPCRequest( new rXMLRPCCommand("to_kb", floatval(1024)) );
			if($req->run())
			{
				if(!$req->fault)
					$this->badXMLRPCVersion = false;
				$req = new rXMLRPCRequest( array(
					new rXMLRPCCommand("get_directory"),
					new rXMLRPCCommand("get_session"),
					new rXMLRPCCommand("system.library_version"),
					new rXMLRPCCommand("set_xmlrpc_size_limit",67108863),
					new rXMLRPCCommand("get_name"),
					new rXMLRPCCommand("get_port_range"),
					) );
				if($req->run() && !$req->fault)
				{
					$this->directory = $req->val[0];
  		        	        $this->session = $req->val[1];
					$this->libVersion = $req->val[2];
					$this->server = $req->val[4];
					$this->portRange = $req->val[5];
					if(isLocalMode())
					{
	                                        if(!empty($this->session))
	                                        {
							$this->started = @filemtime($this->session.'/rtorrent.lock');
							if($this->started===false)
								$this->started = 0;
						}
						$randName = uniqid("/tmp/rutorrent-stats-".rand());
						$id = getExternal('id');
						$req = new rXMLRPCRequest(
        						new rXMLRPCCommand("execute",array("sh","-c",$id." -u > ".$randName." ; ".$id." -G >> ".$randName." ; echo ~ >> ".$randName." ; chmod 0644 ".$randName)));
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
						else
							$this->idNotFound = true;
					}
					$this->store();
				}
			}
		}
	}
	public function getCommand($cmd)
	{
	        $add = '';
		$len = strlen($cmd);
		if($len && ($cmd[$len-1]=='='))
		{
			$cmd = substr($cmd,0,-1);
			$add = '=';
		}
		return(array_key_exists($cmd,$this->aliases) ? $this->aliases[$cmd].$add : $cmd.$add);		
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