<?php
require_once( dirname(__FILE__)."/../../php/xmlrpc.php" );
require_once( $rootPath.'/php/cache.php');
eval(getPluginConf('throttle'));

@define('MAX_SPEED', 100*1024*1024);

class rThrottle
{
	public $hash = "throttle.dat";
	public $thr = array();
	public $default = 0;

	static public function load()
	{
		$cache = new rCache();
		$rt = new rThrottle();
		if(!$cache->get($rt) || (count($rt->thr)!=MAX_THROTTLE))
			$rt->fillArray();
		return($rt);
	}
	public function fillArray()
	{
		$this->thr = array();
		$v = 16;
	        for($i=0; $i<MAX_THROTTLE/2; $i++)
	        {
			$this->thr[] = array( "up"=>$v, "down"=>0, "name"=>"up".$v );
			$v = $v*2;
		}	
		$v = 16;
	        for($i=0; $i<MAX_THROTTLE/2; $i++)
	        {
			$this->thr[] = array( "up"=>0, "down"=>$v, "name"=>"down".$v );
			$v = $v*2;
		}
		$this->default = 0;
	}
	public function isCorrect($no)
	{
		return( ($no>=0) &&
			($no<count($this->thr)) &&
		        ($this->thr[$no]["name"]!="") && 
			($this->thr[$no]["up"]>=0) &&
			($this->thr[$no]["down"]>=0) );
	}
	public function isThrottled($no)
	{
		return( ($no<count($this->thr)) &&
		        ($this->thr[$no]["name"]!="") && 
			(($this->thr[$no]["up"]>0) || ($this->thr[$no]["down"]>0)) );
	}
	public function init()
	{
		$req = new rXMLRPCRequest();
		for($i=0; $i<MAX_THROTTLE; $i++)
		{
			if($this->isCorrect($i))
			{
				$up = $this->thr[$i]["up"];
				$down = $this->thr[$i]["down"];
			}
			else
			{
				$up = 0;
				$down = 0;
			}
			$req->addCommand(new rXMLRPCCommand("throttle_up", array("thr_".$i,$up."")));
			$req->addCommand(new rXMLRPCCommand("throttle_down", array("thr_".$i,$down."")));
		}

		if($this->isCorrect($this->default-1))
			$req->addCommand(rTorrentSettings::get()->getOnInsertCommand(array('_throttle'.getUser(), 
				getCmd('branch').'=$'.getCmd('not').'=$'.getCmd("d.get_throttle_name").'=,'.getCmd('d.set_throttle_name').'=thr_'.($this->default-1))));
		else
			$req->addCommand(rTorrentSettings::get()->getOnInsertCommand(array('_throttle'.getUser(), getCmd('cat='))));
		return($req->run() && !$req->fault);
	}
	public function correct()
	{
		$toCorrect = array();
		$req = new rXMLRPCRequest( 
			new rXMLRPCCommand( "d.multicall", array(
			        "",
				getCmd("d.get_hash="),
				getCmd("d.get_throttle_name="),
				getCmd('cat').'=$'.getCmd("get_throttle_up_max").'=$'.getCmd("d.get_throttle_name="),
				getCmd('cat').'=$'.getCmd("get_throttle_down_max").'=$'.getCmd("d.get_throttle_name=")))
			);
		if($req->run() && !$req->fault)
		{
			for($i=0; $i<count($req->val); $i+=4)
			{
				if(substr($req->val[$i+1],0,4)=="thr_")
				{
					$no = intval(substr($req->val[$i+1],4));
					if(($req->val[$i+2]==="-1") && ($req->val[$i+3]==="-1") &&
						$this->isThrottled($no))
						$toCorrect[$req->val[$i]] = $req->val[$i+1];
				}
			}
			if($this->init())
			{
				$req = new rXMLRPCRequest();
				foreach($toCorrect as $hash=>$name)
				{
					$req->addCommand(new rXMLRPCCommand( "branch", array(
						$hash, 
						getCmd("d.is_active="), 
						getCmd('cat').'=$'.getCmd("d.stop").'=,$'.getCmd("d.set_throttle_name=").$name.',$'.getCmd('d.start='), 
						getCmd('d.set_throttle_name=').$name )));
				}
				if($req->getCommandsCount())
				{
					return($req->run() && !$req->fault);
				}
                                return(true);
                	}
        	}
		return(false);
	}
	public function obtain()
	{
		$req = new rXMLRPCRequest( array(
			new rXMLRPCCommand( "get_upload_rate" ),
			new rXMLRPCCommand( "get_download_rate" ) ));
		if($req->run() && !$req->fault)
		{
			$req1 = new rXMLRPCRequest();
			if($req->val[0]==0)
				$req1->addCommand(new rXMLRPCCommand( "set_upload_rate", MAX_SPEED ));
			if($req->val[1]==0)
				$req1->addCommand(new rXMLRPCCommand( "set_download_rate", MAX_SPEED ));
			if((($req->val[0]==0) || ($req->val[1]==0)) &&
				(!$req1->run() || $req1->fault))
				return(false);
			return($this->correct());
        	}
		return(false);
	}
	public function store()
	{
		$cache = new rCache();
		return($cache->set($this));
	}
	public function set()
	{
		$this->thr = array();
		$this->default = 0;
		for($i = 0; $i<MAX_THROTTLE; $i++)
		{
			$arr = array( "up"=>0, "down"=>0, "name"=>"" );
			if(isset($_REQUEST['thr_up'.$i]))
			{
				$v = intval($_REQUEST['thr_up'.$i]);
				if($v>=0)
					$arr["up"] = $v;
			}				
			if(isset($_REQUEST['thr_down'.$i]))
			{
				$v = intval($_REQUEST['thr_down'.$i]);
				if($v>=0)
					$arr["down"] = $v;
			}
			if(isset($_REQUEST['thr_name'.$i]))
			{
			        $v = trim($_REQUEST['thr_name'.$i]);
			        if($v!='')
					$arr["name"] = $v;
			}
			$this->thr[] = $arr;
		}
		if(isset($_REQUEST['default']))
			$this->default = intval($_REQUEST['default']);
                $this->store();
		$this->init();
	}
	public function get()
	{
		$ret = "theWebUI.throttles = [";
		foreach($this->thr as $item)
			$ret.="{ up: ".$item["up"].", down: ".$item["down"].", name : ".quoteAndDeslashEachItem($item["name"])." },";
		$len = strlen($ret);
		if($ret[$len-1]==',')
			$ret = substr($ret,0,$len-1);
		return($ret."];\ntheWebUI.maxThrottle = ".MAX_THROTTLE.";\ntheWebUI.defaultThrottle = ".$this->default.";\n");
	}
}
