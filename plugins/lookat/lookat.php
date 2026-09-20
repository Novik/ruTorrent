<?php
require_once( dirname(__FILE__)."/../../php/cache.php" );
require_once( dirname(__FILE__)."/../../php/utility/externalurl.php" );

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
					// plugins/lookat/init.js, which hands it to
					// openExternalURL() -- the same call a feed item reaches --
					// so what may be stored here is what that will open. set()
					// rebuilds the whole list from the body, so a target this
					// refuses is deleted rather than rejected.
					if(count($tmp) > 1 && ExternalURL::isOpenable($tmp[1]))
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
