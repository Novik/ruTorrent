<?php

require_once( dirname(__FILE__).'/../../php/cache.php');
require_once( dirname(__FILE__).'/../../php/util.php');
require_once( dirname(__FILE__).'/../../php/settings.php');

class rHistoryData
{
	public $hash = 'history_data.dat';
	public $data = array();

	static public function load()
	{
		$cache = new rCache();
		$ar = new rHistoryData();
		$cache->get($ar);
		return($ar);
	}
	public function store()
	{
		$cache = new rCache();
		return($cache->set($this));
	}
	public function delete( $hashes )
	{
		foreach( $hashes as $hash )
			unset($this->data[$hash]);
		$this->store();
	}
	public function add( $e, $limit )
	{
		$e["action_time"] = time();
		$e["hash"] = md5(serialize($e));
		$this->data[$e["hash"]] = $e;
		uasort($this->data, create_function( '$a,$b', 'return $b["action_time"]-$a["action_time"];' ));
		$count = count($this->data);
		if($limit<3)
			$limit = 500;
		if($count>$limit)
		{
			$keys = array_keys($this->data);
			$values = array_values($this->data);
			$this->data = array_combine(array_splice($keys,$count-1),array_splice($values,$count-1));
		}
		$this->store();
	}
	public function get( $mark )
	{
		$ret = array();
		foreach( $this->data as $hash=>$e )
			if( $e["action_time"]>$mark )
				$ret[] = $e;
			else
				break;
		return(array( "items"=>$ret, "mode"=>($mark==0) ));
	}
}

class rHistory
{
	public $hash = "history.dat";
	public $log = array( "addition"=>1, "finish"=>1, "deletion"=>1, "limit"=>300, "autoclose"=>1, "closeinterval"=>5 );

	static public function load()
	{
		$cache = new rCache();
		$ar = new rHistory();
		if($cache->get($ar) && !array_key_exists("autoclose",$ar->log))
		{
			$ar->log["autoclose"] = 1;
			$ar->log["closeinterval"] = 5;
		}
		return($ar);
	}
	public function store()
	{
		$cache = new rCache();
		return($cache->set($this));
	}
        public function set()
	{
		if(!isset($HTTP_RAW_POST_DATA))
			$HTTP_RAW_POST_DATA = file_get_contents("php://input");
		if(isset($HTTP_RAW_POST_DATA))
		{
			$vars = explode('&', $HTTP_RAW_POST_DATA);
			foreach($vars as $var)
			{
				$parts = explode("=",$var);
				$this->log[$parts[0]] = intval($parts[1]);
  	                }
			$this->store();
			$this->setHandlers();
		}
	}
	public function get()
	{
		return("theWebUI.history = ".json_encode($this->log).";");
	}
	public function setHandlers()
	{
		global $rootPath;
		if($this->log["addition"])
			$addCmd = getCmd('execute').'={'.getPHP().','.$rootPath.'/plugins/history/update.php'.',1,$'.
				getCmd('d.get_name').'=,$'.getCmd('d.get_size_bytes').'=,$'.getCmd('d.get_bytes_done').'=,$'.
				getCmd('d.get_up_total').'=,$'.getCmd('d.get_ratio').'=,$'.getCmd('d.get_creation_date').'=,$'.
				getCmd('d.get_custom').'=addtime,$'.getCmd('d.get_custom').'=seedingtime'.
				',"$'.getCmd('t.multicall').'=$'.getCmd('d.get_hash').'=,'.getCmd('t.get_url').'=,'.getCmd('cat').'=#",$'.
				getCmd('d.get_custom1')."=,".
				getUser().'}';
		else
			$addCmd = getCmd('cat=');
		if($this->log["finish"])
			$finCmd = getCmd('execute').'={'.getPHP().','.$rootPath.'/plugins/history/update.php'.',2,$'.
				getCmd('d.get_name').'=,$'.getCmd('d.get_size_bytes').'=,$'.getCmd('d.get_bytes_done').'=,$'.
				getCmd('d.get_up_total').'=,$'.getCmd('d.get_ratio').'=,$'.getCmd('d.get_creation_date').'=,$'.
				getCmd('d.get_custom').'=addtime,$'.getCmd('d.get_custom').'=seedingtime'.
				',"$'.getCmd('t.multicall').'=$'.getCmd('d.get_hash').'=,'.getCmd('t.get_url').'=,'.getCmd('cat').'=#",$'.
				getCmd('d.get_custom1')."=,".
				getUser().'}';
		else
			$finCmd = getCmd('cat=');
		if($this->log["deletion"])
			$delCmd = getCmd('execute').'={'.getPHP().','.$rootPath.'/plugins/history/update.php'.',3,$'.
				getCmd('d.get_name').'=,$'.getCmd('d.get_size_bytes').'=,$'.getCmd('d.get_bytes_done').'=,$'.
				getCmd('d.get_up_total').'=,$'.getCmd('d.get_ratio').'=,$'.getCmd('d.get_creation_date').'=,$'.
				getCmd('d.get_custom').'=addtime,$'.getCmd('d.get_custom').'=seedingtime'.
				',"$'.getCmd('t.multicall').'=$'.getCmd('d.get_hash').'=,'.getCmd('t.get_url').'=,'.getCmd('cat').'=#",$'.
				getCmd('d.get_custom1')."=,".
				getUser().'}';
		else
			$delCmd = getCmd('cat=');
		$req = new rXMLRPCRequest( array(
			rTorrentSettings::get()->getOnInsertCommand( array('thistory'.getUser(), $addCmd ) ),
			rTorrentSettings::get()->getOnFinishedCommand( array('thistory'.getUser(), $finCmd ) ),
			rTorrentSettings::get()->getOnEraseCommand( array('thistory'.getUser(), $delCmd ) ),
			));
		return($req->success());
	}
}
