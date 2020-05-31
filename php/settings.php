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
	public $apiVersion = 0;
	public $plugins = array();
	public $hooks = array();
	public $aliases = array();
	public $started = 0;
	public $server = '';
	public $portRange = '6890-6999';
	public $port = '6890';
	public $bind = '0.0.0.0';
	public $idNotFound = false;
	public $home = '';
	public $tz = null;
	public $ip = '0.0.0.0';

	static private $theSettings = null;

	private $ratioCmds = array
	(
		"view",          
		"view.set",
		"ratio.min",
		"ratio.min.set",
		"ratio.max",
		"ratio.max.set",
		"ratio.upload",
		"ratio.upload.set",
	);

	private function __construct()
    	{
    		if( array_key_exists("browser_timezone",$_COOKIE) )
    		{
			$this->tz = $_COOKIE["browser_timezone"];
		}
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

	public function registerEventHook( $plugin, $ename, $level = 10, $save = false )
	{
		$subject = array
		(
			"name" => $plugin,
			"level" => $level,
		);

		$sort = function ($a,$b) 
		{ 
			$lvl1 = (float) $a["level"];
			$lvl2 = (float) $b["level"];
			return( $lvl1 > $lvl2 ? 1 : 
				($lvl1 < $lvl2 ? -1 : strcmp($a["name"], $b["name"]) ));
		};

		if(is_array($ename))
		{
			foreach( $ename as $name )
			{
				$this->hooks[$name][] = $subject;
				usort( $this->hooks[$name], $sort );
			}
		}
		else
		{
			$this->hooks[$ename][] = $subject;
			usort( $this->hooks[$ename], $sort );
		}
		// hooks with lesser level runs first
		if( $save )
		{
			$this->store();
		}
	}
	protected function unregisterEventHookPrim( $plugin, $ename )
	{
		for( $i = 0; $i<count($this->hooks[$ename]); $i++ )
		{
			if($this->hooks[$ename][$i] == $plugin)
			{
				unset($this->hooks[$ename][$i]);
				if( empty($this->hooks[$ename]) )
				{
					unset($this->hooks[$ename]);
				}
				break;
			}
		}		
	}
	public function unregisterEventHook( $plugin, $ename, $save = true )
	{
		if(is_array($ename))
		{
			foreach( $ename as $name )
			{
				$this->unregisterEventHookPrim( $plugin, $name );
			}
		}
		else
		{
			$this->unregisterEventHookPrim( $plugin, $ename );
		}
		if( $save )
		{
			$this->store();
		}
	}
	public function pushEvent( $ename, $prm )
	{
		if( array_key_exists($ename,$this->hooks))
		{
			$prm = array($prm);
			foreach( $this->hooks[$ename] as $hook )
			{
				$file = dirname(__FILE__).'/../plugins/'.$hook['name'].'/hooks.php';
				if(is_file($file))
				{
					require_once( $file );
					$func = $hook['name'].'Hooks::On'.$ename;
					if(is_callable( $func ) && 
						(call_user_func_array($func,$prm)==true))
					{
						break;
					}
				}
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

			if($this->iVersion>0x806)
			{
				$this->aliases = array
				(
					"d.set_peer_exchange" 		=> array( "name"=>"d.peer_exchange.set", "prm"=>0 ),
					"d.set_connection_seed"		=> array( "name"=>"d.connection_seed.set", "prm"=>0 ),
				);
			}
			if($this->iVersion==0x808)
			{
				$req = new rXMLRPCRequest( new rXMLRPCCommand("file.prioritize_toc") );
				$req->important = false;
				if($req->success())
					$this->iVersion=0x809;
			}
			if($this->iVersion>=0x904)
			{
				require_once( 'methods-0.9.4.php' );
			}
			
			$this->apiVersion = 0;
			if($this->iVersion>=0x901)
			{
				$req = new rXMLRPCRequest( new rXMLRPCCommand("system.api_version") );
				$req->important = false;
				if($req->success())
					$this->apiVersion = $req->val[0];
			}

			if($this->apiVersion >= 11)	// at current moment (2019.07.20) this is feature-bind branch of rtorrent
			{
				$this->aliases = array_merge($this->aliases,array
				(
					"get_port_open"	=> array( "name"=>"network.listen.is_open", "prm"=>0 ),
					"get_port_random" => array( "name"=>"network.port.randomize", "prm"=>0 ),
					"get_port_range" => array( "name"=>"network.port.range", "prm"=>0 ),
					"set_port_open"	=> array( "name"=>"network.listen.open", "prm"=>1 ),
					"set_port_random" => array( "name"=>"network.port.randomize.set", "prm"=>1 ),
					"set_port_range" => array( "name"=>"network.port.range.set", "prm"=>1 ),
					"network.listen.port" => array( "name"=>"network.port", "prm"=>0 ),
				));
			}

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
					new rXMLRPCCommand("get_bind"),
					new rXMLRPCCommand("get_ip"),
					) );
				if($req->success())
				{
					$this->directory = $req->val[0];
  		        	        $this->session = $req->val[1];
					$this->libVersion = $req->val[2];
					$this->server = $req->val[4];
					$this->portRange = $req->val[5];
					$this->port = intval($this->portRange);
					$this->bind = $req->val[6];
					$this->ip = $req->val[7];

					if($this->iVersion>=0x809)
					{
						$req = new rXMLRPCRequest( new rXMLRPCCommand("network.listen.port") );
						$req->important = false;
						if($req->success())
							$this->port = intval($req->val[0]);
					}

					if(isLocalMode())
					{
	                                        if(!empty($this->session))
	                                        {
							$this->started = @filemtime($this->session.'/rtorrent.lock');
							if($this->started===false)
								$this->started = 0;
						}
						$id = getExternal('id');
						$req = new rXMLRPCRequest(
        						new rXMLRPCCommand("execute_capture",array("sh","-c",$id." -u ; ".$id." -G ; echo ~ ")));
						if($req->run() && !$req->fault && (($line=explode("\n",$req->val[0]))!==false) && (count($line)>2))
						{
							$this->uid = intval(trim($line[0]));
							$this->gid = explode(' ',trim($line[1]));
							$this->home = trim($line[2]);
							if(!empty($this->directory) &&
								($this->directory[0]=='~'))
								$this->directory = $this->home.substr($this->directory,1);	
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
		return(array_key_exists($cmd,$this->aliases) ? $this->aliases[$cmd]["name"].$add : $cmd.$add);		
	}
	public function getRatioGroupCommand($ratio,$cmd,$args)
	{
		$prefix = ($this->iVersion >= 0x904) && in_array($cmd,$this->ratioCmds) ? "group2." : "group.";
		return( new rXMLRPCCommand( $prefix.$ratio.".".$cmd, $args ) );
	}
	public function getEventCommand($cmd1,$cmd2,$args)
	{
		if($this->iVersion<0x804)
			$cmd = new rXMLRPCCommand($cmd1);
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
	public function getOnHashdoneCommand($args)
	{
        	return($this->getEventCommand('on_hash_done','hash_done',$args));
	}
	public function getAbsScheduleCommand($name,$interval,$cmd)	// $interval in seconds
	{
		global $schedule_rand;
		if(!isset($schedule_rand))
			$schedule_rand = 10;
		$startAt = $interval+rand(0,$schedule_rand);
		return( new rXMLRPCCommand("schedule", array( $name.getUser(), $startAt."", $interval."", $cmd )) );
	}
	public function getScheduleCommand($name,$interval,$cmd,&$startAt = null)	// $interval in minutes
	{
		global $schedule_rand;
		if(!isset($schedule_rand))
			$schedule_rand = 10;
		$tm = getdate();
		$startAt = mktime($tm["hours"],
			((integer)($tm["minutes"]/$interval))*$interval+$interval,
			0,$tm["mon"],$tm["mday"],$tm["year"])-$tm[0]+rand(0,$schedule_rand);
		if($startAt<0)
			$startAt = 0;
		$interval = $interval*60;
		return( new rXMLRPCCommand("schedule", array( $name.getUser(), $startAt."", $interval."", $cmd )) );
	}
	public function getRemoveScheduleCommand($name)
	{
		return(	new rXMLRPCCommand("schedule_remove", $name.getUser()) );	
	}
	public function correctDirectory(&$dir,$resolve_links = false)
	{
		global $topDirectory;
		if(strlen($dir) && ($dir[0]=='~'))
			$dir = $this->home.substr($dir,1);
		$dir = fullpath($dir,$this->directory);
		if($resolve_links)
		{
			$path = realpath($dir);
			if(!$path)
				$dir = addslash(realpath(dirname($dir))).basename($dir);
			else
				$dir = $path;	
		}
		return(strpos(addslash($dir),$topDirectory)===0);
	}
	public function patchDeprecatedCommand( $cmd, $name )
	{
		if((array_key_exists($name,$this->aliases) && $this->aliases[$name]["prm"]) || 
			(($this->iVersion>=0x904) && (strpos($cmd->command,"group2.")===0)))
			$cmd->addParameter("");
	}
	public function patchDeprecatedRequest($commands)
	{
		if($this->iVersion>=0x904)
		{
			foreach($commands as $cmd)
			{
				$prefix = '';
				if(strpos($cmd->command, 't.') === 0)
					$prefix = ':t';
				else
				if(strpos($cmd->command, 'p.') === 0)
					$prefix = ':p';
				else
				if(strpos($cmd->command, 'f.') === 0)
					$prefix = ':f';
				if(!empty($prefix) && 
					(count($cmd->params)>1) && 
					(substr($cmd->command, -10) !== '.multicall') &&
					(strpos($cmd->params[0]->value, ':') === false) )
				{
					$cmd->params[0]->value = $cmd->params[0]->value.$prefix.$cmd->params[1]->value;
					array_splice( $cmd->params, 1, 1 );
				}
			}
		}
	}
}
