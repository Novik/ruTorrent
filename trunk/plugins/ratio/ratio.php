<?php

require_once( dirname(__FILE__)."/../../php/xmlrpc.php" );
require_once( $rootPath.'/php/cache.php');
require_once( $rootPath.'/php/settings.php');
eval(getPluginConf('ratio'));

define('RAT_STOP',0);
define('RAT_STOP_AND_REMOVE',1);
define('RAT_ERASE',2);
define('RAT_ERASEDATA',3);

class rRatio
{
	public $hash = "ratio.dat";
	public $rat = array();

	static public function load()
	{
		$cache = new rCache();
		$rt = new rRatio();
		if(!$cache->get($rt))
			$rt->fillArray();
		return($rt);
	}
	public function fillArray()
	{
		$this->rat = array();
	        for($i=0; $i<MAX_RATIO; $i++)
			$this->rat[] = array( "action"=>RAT_STOP, "min"=>100, "max"=>300, "upload"=>20, "name"=>"ratio".$i );
	}
	public function isCorrect($no)
	{
		return( ($no<count($this->rat)) &&
		        ($this->rat[$no]["name"]!=""));

	}
	public function correct()
	{
		$cmd = new rXMLRPCCommand("d.multicall",array("default",getCmd("d.get_hash=")));
		for($i=0; $i<MAX_RATIO; $i++)
			$cmd->addParameters( array( getCmd("d.views.has")."=rat_".$i, getCmd("view.set_not_visible")."=rat_".$i ) );
		$req = new rXMLRPCRequest($cmd);
		$req->setParseByTypes();
		if($req->run() && !$req->fault)
		{
			$req1 = new rXMLRPCRequest();
			foreach($req->strings as $no=>$hash)
			{
			        for($i=0; $i<MAX_RATIO; $i++)
			        {
					if($req->i8s[$no*MAX_RATIO*2+$i*2]==1)
						$req1->addCommand(new rXMLRPCCommand("view.set_visible",array($hash,"rat_".$i)));
				}						
			}
			return(($req1->getCommandsCount()==0) || ($req1->run() && !$req1->fault));
		}
		return(false);
	}
	public function obtain()
	{
        	return($this->flush() && $this->correct());
	}
	public function flush()
	{
		$req1 = new rXMLRPCRequest(new rXMLRPCCommand("view_list"));
		if($req1->run() && !$req1->fault)
		{
			$req = new rXMLRPCRequest();
			for($i=0; $i<MAX_RATIO; $i++)
			{
				$rat = $this->rat[$i];
				if(!in_array("rat_".$i,$req1->val))
					$req->addCommand(new rXMLRPCCommand("group.insert_persistent_view", array("", "rat_".$i)));
				if($this->isCorrect($i))
				{
					$req->addCommand(new rXMLRPCCommand("group.rat_".$i.".ratio.enable",array("")));
					$req->addCommand(new rXMLRPCCommand("group.rat_".$i.".ratio.min.set",$rat["min"]));
					$req->addCommand(new rXMLRPCCommand("group.rat_".$i.".ratio.max.set",$rat["max"]));
					$req->addCommand(new rXMLRPCCommand("group.rat_".$i.".ratio.upload.set",floatval($rat["upload"]*1024*1024)));
					switch($rat["action"])
					{
						case RAT_STOP:
						{
							$req->addCommand(new rXMLRPCCommand("system.method.set", array("group.rat_".$i.".ratio.command", 
								getCmd("d.stop=")."; ".getCmd("d.close="))));
							break;
						}
						case RAT_STOP_AND_REMOVE:
						{
							$req->addCommand(new rXMLRPCCommand("system.method.set", array("group.rat_".$i.".ratio.command", 
								getCmd("d.stop=")."; ".getCmd("d.close=")."; ".getCmd("view.set_not_visible")."=rat_".$i."; ".getCmd("d.views.remove")."=rat_".$i)));
							break;
						}
						case RAT_ERASE:
						{
							$req->addCommand(new rXMLRPCCommand("system.method.set", array("group.rat_".$i.".ratio.command", 
								getCmd("d.stop=")."; ".getCmd("d.close=")."; ".getCmd("d.erase="))));
							break;
						}
						case RAT_ERASEDATA:
						{
							$req->addCommand(new rXMLRPCCommand("system.method.set", array("group.rat_".$i.".ratio.command", 
								getCmd("d.stop=")."; ".getCmd("d.close=")."; ".getCmd("d.set_custom5=")."1; ".getCmd("d.erase="))));
							break;
						}
					}
				}
			}
			return(($req->getCommandsCount()==0) || ($req->run() && !$req->fault));
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
		$this->rat = array();
		for($i = 0; $i<MAX_RATIO; $i++)
		{
			$arr = array( "action"=>RAT_STOP, "min"=>100, "max"=>300, "upload"=>20, "name"=>"" );
			if(isset($_REQUEST['rat_action'.$i]))
				$arr["action"] = intval($_REQUEST['rat_action'.$i]);
			if(isset($_REQUEST['rat_min'.$i]))
			        $arr["min"] = intval($_REQUEST['rat_min'.$i]);
			if(isset($_REQUEST['rat_max'.$i]))
			        $arr["max"] = intval($_REQUEST['rat_max'.$i]);
			if(isset($_REQUEST['rat_upload'.$i]))
			        $arr["upload"] = intval($_REQUEST['rat_upload'.$i]);
			if(isset($_REQUEST['rat_name'.$i]))
			{
			        $v = trim($_REQUEST['rat_name'.$i]);
			        if($v!='')
					$arr["name"] = $v;
			}
			$this->rat[] = $arr;
		}
                $this->store();
		$this->flush();
	}
	public function get()
	{
		$ret = "theWebUI.ratios = [";
		foreach($this->rat as $item)
			$ret.="{ action: ".$item["action"].", min: ".$item["min"].", max: ".$item["max"].", upload: ".$item["upload"].", name : ".quoteAndDeslashEachItem($item["name"])." },";
		$len = strlen($ret);
		if($ret[$len-1]==',')
			$ret = substr($ret,0,$len-1);
		return($ret."];\ntheWebUI.maxRatio = ".MAX_RATIO.";\n");
	}
}

?>