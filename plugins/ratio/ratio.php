<?php

require_once( dirname(__FILE__)."/../../php/xmlrpc.php" );
require_once( $rootPath.'/php/cache.php');
require_once( $rootPath.'/php/settings.php');
eval(FileUtil::getPluginConf('ratio'));

@define('RAT_STOP',0);
@define('RAT_STOP_AND_REMOVE',1);
@define('RAT_ERASE',2);
@define('RAT_ERASEDATA',3);
@define('RAT_ERASEDATAALL',4);
@define('RAT_FIRSTTHROTTLE',10);

class rRatio
{
	public $hash = "ratio.dat";
	public $rat = array();
	public $default = 0;
	private $version = 3.10;

	static public function load()
	{
		$cache = new rCache();
		$rt = new rRatio();
		if(!$cache->get($rt))
		{
			$rt->fillArray();
			$rt->version = 3.11;
		}
		elseif ($rt->version != 3.11)
		{
			$rt->migrate();
			$rt->pad();
			$rt->version = 3.11;
		}
		else
			$rt->pad();
		return($rt);
	}
	private function migrate()
	{
		for($i=0; $i<count($this->rat); $i++)
		{
			$this->rat[$i]["upload"] /= 1024;
		}		
	}
	public function pad()
	{
	        for($i=0; $i<count($this->rat); $i++)
	        {
	        	$rat = &$this->rat[$i];
	        	$rat["min"] = Math::iclamp($rat["min"]);
	        	$rat["max"] = Math::iclamp($rat["max"]);
	        	$rat["upload"] = Math::fRoundClamp($rat["upload"]);
	        }
		for($i=count($this->rat); $i<MAX_RATIO; $i++)
			$this->rat[] = array( "action"=>RAT_STOP, "min"=>100, "max"=>300, "upload"=>0.1, "name"=>"ratio".$i, "time"=>-1 );
	}
	public function fillArray()
	{
		$this->rat = array();
		$this->pad();
		$this->default = 0;
	}
	public function getTimes()
	{
		$ret = array();
		for($i=0; $i<MAX_RATIO; $i++)
			if(array_key_exists("time",$this->rat[$i]) && ($this->rat[$i]["time"]>=0))
				$ret[]=$i;
		return($ret);
	}
	public function hasTimes()
	{
		for($i=0; $i<MAX_RATIO; $i++)
			if(array_key_exists("time",$this->rat[$i]) && ($this->rat[$i]["time"]>=0))
				return(true);
		return(false);
	}
	public function checkTimes()
	{
		$times = $this->getTimes();
		$cnt = count($times);
		if($cnt)
		{
			$cmd = new rXMLRPCCommand("d.multicall",array("complete",getCmd("d.get_hash="),getCmd("d.get_custom=")."seedingtime",getCmd("d.is_active=") ));
			foreach($times as $i)
				$cmd->addParameters( array( getCmd("cat")."=".$i, getCmd("d.views.has")."=rat_".$i) );
			$req = new rXMLRPCRequest($cmd);
			if($req->success())
			{
				$tm = time();
				$req1 = new rXMLRPCRequest();
				for($i=0; $i<count($req->val); $i+=(3+$cnt*2))
				{
					$hash = $req->val[$i];
					$finished = intval($req->val[$i+1]);
					$active = intval($req->val[$i+2]);

					if($active && $finished)
					{
						for($j=0; $j<$cnt*2; $j+=2)
						{
							if(intval($req->val[$i+$j+4])==1)
							{
								$rat = $this->rat[intval($req->val[$i+$j+3])];
								if( $tm>=$finished+$rat["time"]*3600 )
									$req1->addCommand( new rXMLRPCCommand("group.rat_".$req->val[$i+$j+3].".ratio.command",$hash) );
							}
						}
					}
				}
				return(($req1->getCommandsCount()==0) || ($req1->success()));
			}
			return(false);
		}
		return(true);
	}
	public function setHandlers()
	{
		global $checkTimesInterval;
		$req =  new rXMLRPCRequest( $this->hasTimes() ? 
			rTorrentSettings::get()->getAbsScheduleCommand("ratio",$checkTimesInterval*60,
				getCmd('execute').'={sh,-c,'.escapeshellarg(Utility::getPHP()).' '.escapeshellarg(dirname(__FILE__).'/update.php').' '.escapeshellarg(User::getUser()).' &}' ) :
			rTorrentSettings::get()->getRemoveScheduleCommand("ratio") );
		return($req->success());
	}
	public function isCorrect($no)
	{
		return( ($no>=0) && 
			($no<count($this->rat)) &&
		        ($this->rat[$no]["name"]!=""));
	}
	public function correct()
	{
		$cmd = new rXMLRPCCommand("d.multicall",array("default",getCmd("d.get_hash=")));
		for($i=0; $i<MAX_RATIO; $i++)
			$cmd->addParameters( array( getCmd("d.views.has")."=rat_".$i, getCmd("view.set_not_visible")."=rat_".$i ) );
		$req = new rXMLRPCRequest($cmd);
		$req->setParseByTypes();
		if($req->success())
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
			return(($req1->getCommandsCount()==0) || ($req1->success()));
		}
		return(false);
	}
	public function obtain()
	{
        	return($this->flush() && $this->correct() && $this->setHandlers());
	}
	public function flush()
	{
		$req1 = new rXMLRPCRequest(new rXMLRPCCommand("view_list"));
		if($req1->run() && !$req1->fault)
		{
			$insCmd = getCmd('branch=');
			$req = new rXMLRPCRequest();
			for($i=0; $i<MAX_RATIO; $i++)
			{
				$insCmd .= (getCmd('d.views.has=').'rat_'.$i.',,');
				$rat = $this->rat[$i];
				if(!in_array("rat_".$i,$req1->val))
					$req->addCommand(new rXMLRPCCommand("group.insert_persistent_view", array("", "rat_".$i)));
				if($this->isCorrect($i))
				{
					$req->addCommand( rTorrentSettings::get()->getRatioGroupCommand("rat_".$i,'ratio.enable',array("")) );
					$req->addCommand( rTorrentSettings::get()->getRatioGroupCommand("rat_".$i,'ratio.min.set',$rat["min"]) );
					$req->addCommand( rTorrentSettings::get()->getRatioGroupCommand("rat_".$i,'ratio.max.set',$rat["max"]) );
					$req->addCommand( rTorrentSettings::get()->getRatioGroupCommand("rat_".$i,'ratio.upload.set',floatval($rat["upload"]*1024*1024*1024)) );
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
						case RAT_ERASEDATAALL:
						{
							$req->addCommand(new rXMLRPCCommand("system.method.set", array("group.rat_".$i.".ratio.command",
								getCmd("d.stop=")."; ".getCmd("d.close=")."; ".getCmd("d.set_custom5=")."2; ".getCmd("d.erase="))));
							break;
						}
						default:
						{
							$thr = "thr_".($rat["action"]-RAT_FIRSTTHROTTLE);
							$req->addCommand(new rXMLRPCCommand("system.method.set", array("group.rat_".$i.".ratio.command", 
								getCmd('cat').'=$'.getCmd("d.stop").'=,$'.getCmd("d.set_throttle_name=").$thr.',$'.getCmd('d.start='))));
							break;
						}
					}
				}
			}

			if($this->isCorrect($this->default-1))
				$req->addCommand(rTorrentSettings::get()->getOnInsertCommand(array('_ratio'.User::getUser(), 
					$insCmd.getCmd('view.set_visible=').'rat_'.($this->default-1))));
			else
				$req->addCommand(rTorrentSettings::get()->getOnInsertCommand(array('_ratio'.User::getUser(), getCmd('cat='))));

			return($req->run() && !$req->fault);
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
		$this->default = 0;
		for($i = 0; $i<MAX_RATIO; $i++)
		{
			$arr = array( "action"=>RAT_STOP, "min"=>100, "max"=>300, "upload"=>0.1, "name"=>"", "time"=>-1 );
			if(isset($_REQUEST['rat_action'.$i]))
				$arr["action"] = intval($_REQUEST['rat_action'.$i]);
			if(isset($_REQUEST['rat_min'.$i]))
			        $arr["min"] = Math::iclamp($_REQUEST['rat_min'.$i]);
			if(isset($_REQUEST['rat_max'.$i]))
			        $arr["max"] = Math::iclamp($_REQUEST['rat_max'.$i]);
			if(isset($_REQUEST['rat_upload'.$i]))
			        $arr["upload"] = Math::fRoundClamp($_REQUEST['rat_upload'.$i]);
			if(isset($_REQUEST['rat_time'.$i]))
			        $arr["time"] = (is_numeric($_REQUEST['rat_time'.$i]) ? floatval($_REQUEST['rat_time'.$i]) : -1);
			if(isset($_REQUEST['rat_name'.$i]))
			{
			        $v = trim($_REQUEST['rat_name'.$i]);
			        if($v!='')
					$arr["name"] = $v;
			}
			$this->rat[] = $arr;
		}
		if(isset($_REQUEST['default']))
			$this->default = floatval($_REQUEST['default']);
                $this->store();
		$this->flush();
		$this->setHandlers();
	}
	public function get()
	{
		$ret = "theWebUI.ratios = [";
		foreach($this->rat as $item)
		{
			$tm = (array_key_exists("time",$item) ? $item["time"] : -1);
			$ret.="{ action: ".$item["action"].", min: ".$item["min"].", max: ".$item["max"].
				", time: ".$tm.", upload: ".$item["upload"].", name : ".Utility::quoteAndDeslashEachItem($item["name"])." },";
		}
		$len = strlen($ret);
		if($ret[$len-1]==',')
			$ret = substr($ret,0,$len-1);
		return($ret."];\ntheWebUI.maxRatio = ".MAX_RATIO.";\ntheWebUI.defaultRatio = ".$this->default.";\n");
	}
}
