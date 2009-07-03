<?php
$rootPath = "./";
if(!is_file('util.php'))
	$rootPath = "../../";
require_once( $rootPath."util.php" );

class rRetrackers
{
	public $hash = "retrackers.dat";
	public $list = array();
	public $dontAddPrivate = 1;

	static public function load()
	{
		global $settings;
		global $rootPath;
		$cache = new rCache( $rootPath.$settings );
		$rt = new rRetrackers();
		$cache->get($rt);
		return($rt);
	}
	public function store()
	{
		global $settings;
		global $rootPath;
		$cache = new rCache( $rootPath.$settings );
		return($cache->set($this));
	}
	public function set()
	{
		if(!isset($HTTP_RAW_POST_DATA))
			$HTTP_RAW_POST_DATA = file_get_contents("php://input");
		if(isset($HTTP_RAW_POST_DATA))
		{
			$vars = split('&', $HTTP_RAW_POST_DATA);
			$this->list = array(); 
			$this->dontAddPrivate = 0;
			$trackers = array();
			foreach($vars as $var)
			{
				$parts = split("=",$var);
				if($parts[0]=="dont_private")
					$this->dontAddPrivate = $parts[1];
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
		$ret = "utWebUI.retrackers = { dontAddPrivate: ".$this->dontAddPrivate.", trackers: [";
		for($i=0; $i<count($this->list); $i++)
		{
  	                $grp = array_map(  'quoteAndDeslashEachItem',  $this->list[$i]);
			$cnt = count($grp);
			if($cnt)
			{
				$ret.="[";
				$ret.= implode(",",$grp);
				$ret.="],";
			}
		}
		$len = strlen($ret);
		if($ret[$len-1]==',')
			$ret = substr($ret,0,$len-1);
		return($ret."]};\n");
	}
}

?>
