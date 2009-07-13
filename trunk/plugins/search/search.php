<?php
$searchRootPath = "./";
if(!is_file('util.php'))
	$searchRootPath = "../../";
require_once( $searchRootPath."util.php" );

class rSearch
{
	public $hash = "search.dat";
	public $list = array(
		array( "name"=>'Mininova', 		"url"=>'http://www.mininova.org/search/?utorrent&search=' ),
		array( "name"=>'HQTtracker.ru', 	"url"=>'http://hqtracker.ru/browse.php?cat=0&search_in=1&search=' ),
		array( "name"=>'The Pirate Bay',	"url"=>'http://thepiratebay.org/search.php?q=' ),
		array( "name"=>'INTERFILM', 		"url"=>'http://interfilm.nu/movie/?do=search&str=' ),
		array( "name"=>'IsoHunt', 		"url"=>'http://isohunt.com/torrents.php?ext=&op=and&ihq=' ),
		array( "name"=>'VideoTracker.ru',	"url"=>'http://videotracker.ru/browse.php?cat=0&search_in=1&search=' ),
		array( "name"=>'', 			"url"=>'' ),
		array( "name"=>'Google', 		"url"=>'http://google.com/search?q=' ));

	static public function load()
	{
		global $settings;
		global $searchRootPath;
		$cache = new rCache( $searchRootPath.$settings );
		$rt = new rSearch();
		$cache->get($rt);
		return($rt);
	}
	public function store()
	{
		global $settings;
		global $searchRootPath;
		$cache = new rCache( $searchRootPath.$settings );
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
			foreach($vars as $var)
			{
				$parts = split("=",$var);
				if($parts[0]=="site")
				{
					$value = trim(rawurldecode($parts[1]));
					if(strlen($value))
					{
						$tmp = split("\|",$value);
						if(count($tmp>1) && (trim($tmp[1])!=''))
							$this->list[] = array( "name"=>$tmp[0], "url"=>$tmp[1] );
						else
							$this->list[] = array( "name"=>"", "url"=>"" );
					}
				}
			}
		}
		$this->store();
	}
	public function get()
	{
                $ret = "searchSities = [";
		foreach( $this->list as $item )
			$ret.="{ name: ".quoteAndDeslashEachItem($item["name"]).", url: ".quoteAndDeslashEachItem($item["url"])." },";
		$len = strlen($ret);
		if($ret[$len-1]==',')
			$ret = substr($ret,0,$len-1);
		return($ret."];\n");
	}
}

?>
