<?php

require_once( dirname(__FILE__).'/../../php/cache.php');
require_once( dirname(__FILE__).'/../../php/util.php');
require_once( dirname(__FILE__).'/../../php/settings.php');
require_once( dirname(__FILE__).'/../../php/Snoopy.class.inc');
eval(FileUtil::getPluginConf('history'));

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
	static protected function sortByActionTime( $a, $b )
	{
		return($b["action_time"]-$a["action_time"]);
	}
	public function add( $e, $limit )
	{
		$e["action_time"] = time();
		$e["hash"] = md5(serialize($e));
		$this->data[$e["hash"]] = $e;
		uasort($this->data, array(__CLASS__,"sortByActionTime"));
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
	public $log = array
	( 
		"addition"=>1, 
		"finish"=>1, 
		"deletion"=>1, 
		"limit"=>300, 
		"autoclose"=>1, 
		"closeinterval"=>5, 
		"pushbullet_enabled"=>0, 
		"pushbullet_addition"=>0, 
		"pushbullet_finish"=>0, 
		"pushbullet_deletion"=>0,
		"pushbullet_key"=>'',
	);

	static public function load()
	{
		$cache = new rCache();
		$ar = new rHistory();
		if($cache->get($ar))
		{
			if(!array_key_exists("autoclose",$ar->log))
			{
				$ar->log["autoclose"] = 1;
				$ar->log["closeinterval"] = 5;
			}
			if(!array_key_exists("pushbullet_enabled",$ar->log))
			{
				$ar->log["pushbullet_enabled"] = 0;
				$ar->log["pushbullet_addition"] = 0;
				$ar->log["pushbullet_finish"] = 0;
				$ar->log["pushbullet_deletion"] = 0;
				$ar->log["pushbullet_key"] = '';
			}
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
				$this->log[$parts[0]] = ($parts[0]=='pushbullet_key') ? $parts[1] : intval($parts[1]);
  	                }
			$this->store();
			$this->setHandlers();
		}
	}
	public function get()
	{
		return("theWebUI.history = ".JSON::safeEncode($this->log).";");
	}
	public function setHandlers()
	{
		global $rootPath;
		if($this->log["addition"] || ($this->log["pushbullet_enabled"] && $this->log["pushbullet_addition"]))
		{
			$addCmd = getCmd('execute.nothrow.bg').'={'.Utility::getPHP().','.$rootPath.'/plugins/history/update.php'.',1,$'.
				getCmd('d.get_name').'=,$'.getCmd('d.get_size_bytes').'=,$'.getCmd('d.get_bytes_done').'=,$'.
				getCmd('d.get_up_total').'=,$'.getCmd('d.get_ratio').'=,$'.getCmd('d.get_creation_date').'=,$'.
				getCmd('d.get_custom').'=addtime,$'.getCmd('d.get_custom').'=seedingtime'.
				',"$'.getCmd('t.multicall').'=$'.getCmd('d.get_hash').'=,'.getCmd('t.get_url').'=,'.getCmd('cat').'=#",$'.
				getCmd('d.get_custom1')."=,$".getCmd('d.get_custom')."=x-pushbullet,".
				User::getUser().'}';
		}				
		else
			$addCmd = getCmd('cat=');
		if($this->log["finish"] || ($this->log["pushbullet_enabled"] && $this->log["pushbullet_finish"]))
			$finCmd = getCmd('execute.nothrow.bg').'={'.Utility::getPHP().','.$rootPath.'/plugins/history/update.php'.',2,$'.
				getCmd('d.get_name').'=,$'.getCmd('d.get_size_bytes').'=,$'.getCmd('d.get_bytes_done').'=,$'.
				getCmd('d.get_up_total').'=,$'.getCmd('d.get_ratio').'=,$'.getCmd('d.get_creation_date').'=,$'.
				getCmd('d.get_custom').'=addtime,$'.getCmd('d.get_custom').'=seedingtime'.
				',"$'.getCmd('t.multicall').'=$'.getCmd('d.get_hash').'=,'.getCmd('t.get_url').'=,'.getCmd('cat').'=#",$'.
				getCmd('d.get_custom1')."=,$".getCmd('d.get_custom')."=x-pushbullet,".
				User::getUser().'}';
		else
			$finCmd = getCmd('cat=');
		if($this->log["deletion"] || ($this->log["pushbullet_enabled"] && $this->log["pushbullet_deletion"]))
			$delCmd = getCmd('execute.nothrow.bg').'={'.Utility::getPHP().','.$rootPath.'/plugins/history/update.php'.',3,$'.
				getCmd('d.get_name').'=,$'.getCmd('d.get_size_bytes').'=,$'.getCmd('d.get_bytes_done').'=,$'.
				getCmd('d.get_up_total').'=,$'.getCmd('d.get_ratio').'=,$'.getCmd('d.get_creation_date').'=,$'.
				getCmd('d.get_custom').'=addtime,$'.getCmd('d.get_custom').'=seedingtime'.
				',"$'.getCmd('t.multicall').'=$'.getCmd('d.get_hash').'=,'.getCmd('t.get_url').'=,'.getCmd('cat').'=#",$'.
				getCmd('d.get_custom1')."=,$".getCmd('d.get_custom')."=x-pushbullet,".
				User::getUser().'}';
		else
			$delCmd = getCmd('cat=');
		$req = new rXMLRPCRequest( array(
			rTorrentSettings::get()->getOnInsertCommand( array('thistory'.User::getUser(), $addCmd ) ),
			rTorrentSettings::get()->getOnFinishedCommand( array('thistory'.User::getUser(), $finCmd ) ),
			rTorrentSettings::get()->getOnEraseCommand( array('thistory'.User::getUser(), $delCmd ) ),
			));
		return($req->success());
	}

	static protected function bytes( $bt )
	{
		$a = array('B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB');
		$ndx = 0;
		if($bt == 0)
			$ndx = 1;
		else
		{
			if($bt < 1024)
			{
				$bt = $bt / 1024;
				$ndx = 1;
			}
			else
			{
				while($bt >= 1024)
				{
       		    			$bt = $bt / 1024;
      					$ndx++;
	         		}
			}
		}
		return((floor($bt*10)/10)." ".$a[$ndx]);
	}

	public function pushBulletNotify( $data )
	{
		global $pushBulletNotifications, $pushBulletEndpoint;
		$actions = array
		(
			1 => 'addition', 
			2 => 'finish', 
			3 => 'deletion',
		);
		$section = $pushBulletNotifications[$actions[$data['action']]];
		$fields = array
		(
			'{name}', '{label}', '{size}', '{downloaded}', '{uploaded}', '{ratio}',
			'{creation}', '{added}', '{finished}', '{tracker}',
		);
		if( !is_null(rTorrentSettings::get()->tz) )
		{
			$tz = date_default_timezone_get();
			date_default_timezone_set(rTorrentSettings::get()->tz);
		}
		$values = array
		(
			$data['name'], 
			$data['label'], 
			self::bytes($data['size']), 
			self::bytes($data['downloaded']), 
			self::bytes($data['uploaded']), 
			$data['ratio'],
			strftime('%c',$data['creation']), 
			strftime('%c',$data['added']),
			strftime('%c',$data['finished']),
			$data['tracker'],
		);
		if( !is_null(rTorrentSettings::get()->tz) )
		{
			date_default_timezone_set($tz);
		}
		$title = str_replace( $fields, $values, $section['title'] );
		$body = str_replace( $fields, $values, $section['body'] );
		$client = new Snoopy();
		$client->user = $this->log["pushbullet_key"];
		$client->fetch($pushBulletEndpoint,"POST","application/json", JSON::safeEncode(array
		(
			'type'=>'note',
			'title'=>$title,
			'body'=>$body,
		)));
	}
}
