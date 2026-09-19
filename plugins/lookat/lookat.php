<?php
require_once( dirname(__FILE__)."/../../php/cache.php" );

class rLook
{
	public $hash = "look.dat";
	public $modified = false;
	public $list = array();

	static public function load()
	{
		$cache = new rCache();
		$rt = new rLook();
		if(!$cache->get($rt))
		{
			$rt->list["Google"] = "https://www.google.com/search?q={title}";
			$rt->list["IMDb"] = "https://www.imdb.com/find/?q={title}";
			$rt->list["MetaCritic"] = "https://www.metacritic.com/search/{title}";
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
	// $body defaults to the request body; a caller may pass one instead.
	public function set( $body = null )
	{
		if(is_null($body))
			$body = file_get_contents("php://input");
		$vars = explode('&', $body);
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
					// The template is completed and opened by
					// plugins/lookat/init.js, so only an http(s) target is
					// stored; nothing downstream checks it again.
					if(count($tmp) > 1 && preg_match('|^\s*https?://|i',$tmp[1]))
					{
						if(strpos($tmp[1],"{title}")===false)
							$tmp[1].="{title}";
						$this->list[$tmp[0]] = $tmp[1];
					}
				}
			}
		}
		$this->store();
	}
	public function get()
	{
		return(JSON::safeEncode($this->list));
	}
}
