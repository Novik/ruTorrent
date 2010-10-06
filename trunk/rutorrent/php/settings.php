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
	public $idNotFound = false;

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

	static public function load()
	{
		$cache = new rCache();
		$rts = new rTorrentSettings();
		$cache->get($rts);
		return($rts);
	}
	public function store()
	{
		$cache = new rCache();
		return($cache->set($this));
	}
	static public function get()
	{
		global $theSettings;
		if(!isset($theSettings))
			$theSettings = rTorrentSettings::load();
		return($theSettings);
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
/*
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
			if($this->mostOfMethodsRenamed)
			{
				$this->aliases = array(
					"d.get_base_filename" 		=> "d.base_filename",
					"d.get_base_path" 		=> "d.base_path",
					"d.get_bitfield" 		=> "d.bitfield",
					"d.get_creation_date" 		=> "d.creation_date",
					"d.get_down_rate" 		=> "d.down.rate",
					"d.get_down_total" 		=> "d.down.total",
					"d.get_hash" 			=> "d.hash",
					"d.get_local_id" 		=> "d.local_id",
					"d.get_local_id_html" 		=> "d.local_id_html",
					"d.get_name" 			=> "d.name",
					"d.get_peer_exchange" 		=> "d.peer_exchange",
					"d.get_skip_rate" 		=> "d.skip.rate",
					"d.get_skip_total" 		=> "d.skip.total",
					"d.get_up_rate" 		=> "d.up.rate",
					"d.get_up_total" 		=> "d.up.total",
					"d.save_session" 		=> "d.save_full_session",
					"d.set_peer_exchange" 		=> "d.peer_exchange.set",
					"get_handshake_log" 		=> "log.handshake",
					"get_log.tracker" 		=> "log.tracker",
					"get_max_file_size" 		=> "system.file.max_size",
					"get_max_memory_usage" 		=> "pieces.memory.max",
					"get_memory_usage" 		=> "pieces.memory.current",
					"get_name" 			=> "system.session_name",
					"get_preload_min_size" 		=> "pieces.preload.min_size",
					"get_preload_required_rate" 	=> "pieces.preload.min_rate",
					"get_preload_type" 		=> "pieces.preload.type",
					"get_safe_free_diskspace" 	=> "pieces.sync.safe_free_diskspace",
					"get_safe_sync" 		=> "pieces.sync.always_safe",
					"get_session_lock"		=> "system.session.use_lock",
					"get_session_on_completion" 	=> "system.session.on_completion",
					"get_split_file_size" 		=> "system.file.split_size",
					"get_split_suffix" 		=> "system.file.split_suffix",
					"get_stats_not_preloaded" 	=> "pieces.stats_not_preloaded",
					"get_stats_preloaded" 		=> "pieces.stats_preloaded",
					"get_timeout_safe_sync" 	=> "pieces.sync.timeout_safe",
					"get_timeout_sync" 		=> "pieces.sync.timeout",
					"set_handshake_log" 		=> "log.handshake.set",
					"set_log.tracker" 		=> "log.tracker.set",
					"set_max_file_size"		=> "system.file.max_size.set",
					"set_max_memory_usage"		=> "pieces.memory.max.set",
					"set_name" 			=> "system.session_name.set",
					"set_preload_min_size" 		=> "pieces.preload.min_size.set",
					"set_preload_required_rate" 	=> "pieces.preload.min_rate.set",
					"set_preload_type" 		=> "pieces.preload.type.set",
					"set_safe_sync" 		=> "pieces.sync.always_safe.set",
					"set_session_lock" 		=> "system.session.use_lock.set",
					"set_session_on_completion" 	=> "system.session.on_completion.set",
					"set_split_file_size" 		=> "system.file.split_size.set",
					"set_split_suffix" 		=> "system.file.split_suffix.set",
					"set_timeout_safe_sync" 	=> "pieces.sync.timeout_safe.set",
					"set_timeout_sync" 		=> "pieces.sync.timeout.set",
					"system.method.erase" 		=> "method.erase",
					"system.method.get" 		=> "method.get",
					"system.method.has_key" 	=> "method.has_key",
					"system.method.insert" 		=> "method.insert",
					"system.method.list_keys" 	=> "method.list_keys",
					"system.method.set" 		=> "method.set",
					"system.method.set_key" 	=> "method.set_key"
				);
			}
*/
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
					) );
				if($req->run() && !$req->fault)
				{
					$this->directory = $req->val[0];
  		        	        $this->session = $req->val[1];
					$this->libVersion = $req->val[2];
					$this->server = $req->val[4];
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