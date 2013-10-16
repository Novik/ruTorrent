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
			$rt->list["YouTube"] = "http://www.youtube.com/results?search_query={title}";
			$rt->list["MetaCritic"] = "http://www.metacritic.com/search/all/{title}/results";
			$rt->list["IMDb"] = "http://www.imdb.com/search/title?title={title}";
			$rt->list["Google"] = "https://www.google.com/search?q={title}";
			$rt->list["TMDb"] = "http://www.themoviedb.org/search?query={title}";
			$rt->list["TheTVDb"] = "http://thetvdb.com/?string={title}&searchseriesid=&tab=listseries&function=Search";
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
		return(json_encode($this->list));
	}
}
