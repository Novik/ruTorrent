<?php
require_once( dirname(__FILE__)."/../../php/cache.php" );

class rLook
{
	public $hash = "look.dat";
	public $list = array();

	static public function load()
	{
		$cache = new rCache();
		$rt = new rLook();
		if(!$cache->get($rt))
		{
			$rt->list["Google"] = "https://www.google.com/search?q={title}";
			$rt->list["IMDb"] = "https://www.imdb.com/find?q={title}";
			$rt->list["MetaCritic"] = "https://www.metacritic.com/search/all/{title}/results";
			$rt->list["TMDb"] = "https://www.themoviedb.org/search?query={title}";
			$rt->list["TheTVDb"] = "https://www.thetvdb.com/search?query={title}";
			$rt->list["YouTube"] = "https://www.youtube.com/results?search_query={title}";
		}
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
			foreach($vars as $var)
			{
				$parts = explode("=",$var);
				if($parts[0]=="look")
				{
					$value = trim(rawurldecode($parts[1]));
					if(strlen($value))
					{
						$tmp = explode("|",$value);
						if(count($tmp>1) && (trim($tmp[1])!=''))
						{
							if(strpos($tmp[1],"{title}")===false)
								$tmp[1].="{title}";
							$this->list[$tmp[0]] = $tmp[1];
						}
					}
				}
			}
		}
		$this->store();
	}
	public function get()
	{
		return(safe_json_encode($this->list));
	}
}
