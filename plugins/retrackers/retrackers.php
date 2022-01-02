<?php
require_once( dirname(__FILE__)."/../../php/cache.php" );

class rRetrackers
{
	public $hash = "retrackers.dat";
	public $list = array();
	public $todelete = array();
	public $dontAddPrivate = 1;
	public $addToBegin = 0;

	static public function load()
	{
		$cache = new rCache();
		$rt = new rRetrackers();
		$cache->get($rt);
		if(!isset($rt->todelete))
			$rt->todelete = array();
		return($rt);
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
			$this->list = array(); 
			$this->todelete = array();
			$this->dontAddPrivate = 0;
			$trackers = array();
			foreach($vars as $var)
			{
				$parts = explode("=",$var);
				if($parts[0]=="dont_private")
					$this->dontAddPrivate = $parts[1];
				else
				if($parts[0]=="add_begin")
					$this->addToBegin = $parts[1];
				else
				if($parts[0]=="todelete")
					$this->todelete[] = trim(rawurldecode($parts[1]));
				else
				if($parts[0]=="tracker")
				{
					$value = trim(rawurldecode($parts[1]));
					if(strlen($value))
						$trackers[] = $value;
					else
					{
						if(count($trackers)>0)
						{
							$this->list[] = $trackers;
							$trackers = array();
						}
					}
				}
			}
			if(count($trackers)>0)
				$this->list[] = $trackers;
		}
		$this->store();
	}
	public function get()
	{
		return("theWebUI.retrackers = ".JSON::safeEncode($this).";\n");
	}
}
