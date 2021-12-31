<?php
require_once( "util.php" );
require_once( "xmlrpc.php" );

class RemoteRequests {	

	private $remoteRequests = array();	
	private $external = null;
	
	public function __construct(ExternalPath $path)
	{
		$this->external = $path;
	}
	
	public function findRemoteEXE( $exe, $err)
	{
		if(!array_key_exists($exe,$this->remoteRequests))
		{
			$path=realpath(dirname('.'));
			$st = getSettingsPath().'/'.rand();
			$cmd = array( "sh", addslash($path)."test.sh", $exe, $st );
			
			if($this->external->isExternalPathSet($exe))
				$cmd[] = $this->external->getExternalPath($exe);
			
			$req = new rXMLRPCRequest(new rXMLRPCCommand("execute", $cmd));
			$req->run();
			$this->remoteRequests[$exe] = array( "path"=>$st, "err"=>array() );
		}
		$this->remoteRequests[$exe]["err"][] = $err;
	}

	public function getJResultErrorString()
	{
		$ret = "";
		foreach($this->remoteRequests as $exe=>$info)
		{
			$file = $info["path"].$exe.".found";
			if(!is_file($file))
			{
				foreach($info["err"] as $err)
					$ret.=$err;
			}
			else
				@unlink($file);
		}			
		
		return($ret);
	}
}
