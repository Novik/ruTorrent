<?php
rThrottle::$rootPath = "./";
$throttleThisPath = "./plugins/throttle/";
if(!is_file('util.php'))
{
	rThrottle::$rootPath = "../../";
	$throttleThisPath = "./";
}
require_once( $throttleRootPath."xmlrpc.php" );
require_once( $throttleThisPath."conf.php" );

define('MAX_SPEED', 100*1024*1024);

class rThrottle
{
	public $hash = "throttle.dat";
	public $thr = array();
	static public $rootPath;

	static public function load()
	{
		global $settings;
		$cache = new rCache( self::$rootPath.$settings );
		$rt = new rThrottle();
		if(!$cache->get($rt))
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
	}
	public function isCorrect($no)
	{
		return( ($no<count($this->thr)) &&
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
		return($req->run() && !$req->fault);
	}
	public function correct()
	{
		global $isAutoStart;
		if(!$isAutoStart)
			return($this->init());
		$toCorrect = array();
		$req = new rXMLRPCRequest( 
			new rXMLRPCCommand( "d.multicall", array(
			        "",
				"d.get_hash=",
				"d.get_throttle_name=",
				'cat=$get_throttle_up_max=$d.get_throttle_name=',
				'cat=$get_throttle_down_max=$d.get_throttle_name='))
			);
		if($req->run() && !$req->fault)
		{
			for($i=0; $i<count($req->strings); $i+=4)
			{
				if(substr($req->strings[$i+1],0,4)=="thr_")
				{
					$no = intval(substr($req->strings[$i+1],4));
					if(($req->strings[$i+2]==="-1") && ($req->strings[$i+3]==="-1") &&
						$this->isThrottled($no))
						$toCorrect[$req->strings[$i]] = $req->strings[$i+1];
				}
			}
			if($this->init())
			{
				$req = new rXMLRPCRequest();
				foreach($toCorrect as $hash=>$name)
				{
					$req->addCommand(new rXMLRPCCommand( "branch", array(
						$hash, "d.is_active=", 'cat=$d.stop=,$d.set_throttle_name='.$name.',$d.start=', 'd.set_throttle_name='.$name )));
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
			if($req->i8s[0]==0)
				$req1->addCommand(new rXMLRPCCommand( "set_upload_rate", MAX_SPEED ));
			if($req->i8s[1]==0)
				$req1->addCommand(new rXMLRPCCommand( "set_download_rate", MAX_SPEED ));
			if((($req->i8s[0]==0) || ($req->i8s[1]==0)) &&
				(!$req1->run() || $req1->fault))
				return(false);
			return($this->correct());
        	}
		return(false);
	}
	public function store()
	{
		global $settings;
		global $throttleRootPath;
		$cache = new rCache( $throttleRootPath.$settings );
		return($cache->set($this));
	}
	public function set()
	{
		$this->thr = array();
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
                $this->store();
		$this->init();
	}
	public function get()
	{
		$ret = "utWebUI.throttles = [";
		foreach($this->thr as $item)
			$ret.="{ up: ".$item["up"].", down: ".$item["down"].", name : ".quoteAndDeslashEachItem($item["name"])." },";
		$len = strlen($ret);
		if($ret[$len-1]==',')
			$ret = substr($ret,0,$len-1);
		return($ret."];\nutWebUI.maxThrottle = ".MAX_THROTTLE.";\n");
	}
}

?>
