<?php

require_once( dirname(__FILE__)."/../../php/xmlrpc.php" );
require_once( $rootPath.'/php/cache.php');
eval(FileUtil::getPluginConf('scheduler'));

@define('SCH_FAST', 0);
@define('SCH_STOP', 1);
@define('SCH_SEEDONLY', 2);
@define('SCH_RESTRICT1', 3);
@define('SCH_RESTRICT2', 4);
@define('SCH_RESTRICT3', 5);

class rScheduler
{
	public $hash = "scheduler.dat";
	public $enabled = 0;
	public $UL = array();
	public $DL = array();
	public $week = array();
	public $seedStopped = array();
	public $leechStopped = array();

	static public function load()
	{
		$cache = new rCache();
		$rt = new rScheduler();
		if(!$cache->get($rt))
			$rt->fillWeek();
		return($rt);
	}
	public function fillWeek()
	{
		$this->week = array();
		for($i=0; $i<7; $i++)
			$this->week[] = array_fill(0,24,SCH_FAST);
		$this->enabled = 0;
		$this->UL = array_fill(0,3,1);
		$this->DL = array_fill(0,3,1);
	}
	public function store()
	{
		$cache = new rCache();
		return($cache->set($this));
	}
	public function get()
	{
		$ret = "theWebUI.scheduleTable = { UL : [".implode(",",$this->UL)."], DL : [".implode(",",$this->DL)."], enabled : ".$this->enabled.", week : [";
		foreach($this->week as $item)
			$ret.="[".implode(",",$item)."],";
		$len = strlen($ret);
		if($ret[$len-1]==',')
			$ret = substr($ret,0,$len-1);
		return($ret."]};\n");
	}
	public function set()
	{
		$this->fillWeek();
		for($i = 0; $i<7; $i++)
		{
			for($j = 0; $j<24; $j++)
			{
				if(isset($_REQUEST['day_'.$i."_".$j]))
					$this->week[$i][$j] = $_REQUEST['day_'.$i."_".$j];
			}
		}
		if(isset($_REQUEST['UL0']))
			$this->UL[0] = intval($_REQUEST['UL0']);
		if(isset($_REQUEST['DL0']))
			$this->DL[0] = intval($_REQUEST['DL0']);
		if(isset($_REQUEST['UL1']))
			$this->UL[1] = intval($_REQUEST['UL1']);
		if(isset($_REQUEST['DL1']))
			$this->DL[1] = intval($_REQUEST['DL1']);
		if(isset($_REQUEST['UL2']))
			$this->UL[2] = intval($_REQUEST['UL2']);
		if(isset($_REQUEST['DL2']))
			$this->DL[2] = intval($_REQUEST['DL2']);
		if(isset($_REQUEST['enabled']))
			$this->enabled = $_REQUEST['enabled'];
                $this->apply();
	}
	static public function setSpeed( $ul, $dl )
	{
		$req = new rXMLRPCRequest(
			array(
				new rXMLRPCCommand( "set_upload_rate", intval($ul)*1024 ),
				new rXMLRPCCommand( "set_download_rate", intval($dl)*1024 ) ) );
		return($req->run() && !$req->fault);
	}
	static public function processArray( $hashes, $cmd1, $cmd2 )
	{
		$req = new rXMLRPCRequest();
		foreach($hashes as $hash=>$val)
		{
			$req->addCommand(new rXMLRPCCommand( $cmd1, $hash ));
			$req->addCommand(new rXMLRPCCommand( $cmd2, $hash ));
		}
		return($req->run() && !$req->fault);
	}
	static public function getActiveTorrents()
	{
		$delta = 2;
		$cmd = new rXMLRPCCommand("d.multicall", array("started",getCmd("d.get_hash="),getCmd("d.get_connection_current=") ));
		if(rTorrentSettings::get()->iVersion>=0x805)
		{
			$cmd->addParameter( getCmd("d.get_custom=")."sch_ignore" );
			$delta = 3;
		}
		$req = new rXMLRPCRequest(  $cmd );
		$seeds = array();
		$leeches = array();
		if($req->run() && !$req->fault)
		{
			for($i=0; $i<count($req->val); $i+=$delta)
			{
				$hash = $req->val[$i];
				if( ($delta==2) || empty($req->val[$i+2]) )
				{
					if($req->val[$i+1]=='leech')
						$leeches[$hash] = true;
					else
						$seeds[$hash] = true;
				}
			}
		}
		return( array("seeds"=>$seeds,"leeches"=>$leeches) );
	}
	public function apply()
	{
		if($this->enabled)
		{
			$di = getdate();
			$wDay = $di["wday"] - 1;
			if($wDay<0)
				$wDay = 6;
			$no = $this->week[$wDay][$di["hours"]];
			switch($no)
			{
				case SCH_FAST:
				{
					self::processArray(array_merge($this->seedStopped,$this->leechStopped),"d.open","d.start");
					$this->seedStopped = array();
					$this->leechStopped = array();
					self::setSpeed( SCH_DEF_UL, SCH_DEF_DL );
					break;
				}
				case SCH_RESTRICT1:
				case SCH_RESTRICT2:
				case SCH_RESTRICT3:
				{
					self::processArray(array_merge($this->seedStopped,$this->leechStopped),"d.open","d.start");
					$this->seedStopped = array();
					$this->leechStopped = array();
					self::setSpeed( $this->UL[$no-SCH_RESTRICT1], $this->DL[$no-SCH_RESTRICT1] );
					break;
				}
				case SCH_STOP:
				{
					$active = self::getActiveTorrents();
					if(self::processArray(array_merge($active["seeds"],$active["leeches"]),"d.stop","d.close"))
					{
						$this->leechStopped = array_merge($this->leechStopped,$active["leeches"]);
						$this->seedStopped = array_merge($this->seedStopped,$active["seeds"]);
					}
					self::setSpeed( 1, 1 );
  			                break;
				}
				case SCH_SEEDONLY:
				{
					$active = self::getActiveTorrents();
					if(self::processArray($active["leeches"],"d.stop","d.close"))
						$this->leechStopped = array_merge($this->leechStopped,$active["leeches"]);
					self::processArray($this->seedStopped,"d.open","d.start");
					$this->seedStopped = array();
					self::setSpeed( SCH_DEF_UL, 1 );
					break;
				}
			}
		}
                $this->store();
	}
}
