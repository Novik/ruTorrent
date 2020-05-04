<?php

class RUTrackerEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>50, "auth"=>1 );

	public $categories = array( 'all'=>"&f[]=-1" );

	protected function parseTList($results,&$added,&$ret,$limit)
	{
		if( strpos($results, "class=\"f-name\"")===false )
			return(false);
		$res = preg_match_all('/<div class="f-name".*'.
			'href="tracker\.php\?f=\d+">(?P<cat>.*)<\/a>.*'.
			'href="viewtopic\.php\?t=(?P<id>\d+)">(?P<name>.*)<\/a>.*'.
			'dl-stub" href="(?P<link>.*)">(?P<size>.*)<.*'.
			'seedmed.*>(?P<seeds>.*)<.*'.
			'leechmed.*>(?P<leech>.*)<'.
			' data-ts_text="(?P<date>.*)">'.
			'/siU', $results, $matches);
		if($res)
		{
			for($i=0; $i<$res; $i++)
			{
				$link = $matches["link"][$i];
				if(!array_key_exists($link,$ret))
				{
					$item = $this->getNewEntry();
					$item["cat"] = self::toUTF(self::removeTags($matches["cat"][$i],"CP1251"),"CP1251");
					$item["desc"] = "https://rutracker.org/forum/viewtopic.php?t=".$matches["id"][$i];
					$item["name"] = self::toUTF(self::removeTags($matches["name"][$i],"CP1251"),"CP1251");
					$item["size"] = self::formatSize(trim($matches["size"][$i]));
					$item["time"] = floatval($matches["date"][$i]);
					$item["seeds"] = intval(self::removeTags($matches["seeds"][$i]));
					$item["peers"] = intval(self::removeTags($matches["leech"][$i]));
					if (substr($link, 0, 2) === 'dl') {
						$link = 'https://rutracker.org/forum/'.$link;
					}
					$ret[$link] = $item;
					$added++;
					if($added>=$limit)
						return(false);
				}
			}
			return(true);
		}
		else
			return(false);
	}

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://rutracker.org';
		if($useGlobalCats)
			$categories = array( 'all'=>'&f[]=-1',
				'games'=>'&f[]=5&f[]=635&f[]=139&f[]=959&f[]=127&f[]=53&f[]=1008&f[]=51&f[]=961&f[]=962&f[]=54&f[]=55&f[]=52&f[]=900&f[]=246&f[]=278&f[]=128&f[]=2115&f[]=2116&f[]=2117&f[]=2118&f[]=2119&f[]=50&f[]=2142&f[]=2143&f[]=2145&f[]=2146&f[]=637&f[]=642&f[]=643&f[]=644&f[]=645&f[]=646&f[]=647&f[]=649&f[]=1098&f[]=650&f[]=548&f[]=129&f[]=908&f[]=357&f[]=510&f[]=887&f[]=1116&f[]=973&f[]=773&f[]=774&f[]=968&f[]=546',
				'anime'=>'&f[]=33&f[]=281&f[]=1386&f[]=1387&f[]=1388&f[]=282&f[]=599&f[]=1105&f[]=1389&f[]=404&f[]=1390&f[]=1642&f[]=1391&f[]=893&f[]=1478',
				'pictures'=>'f[]=1100&f[]=1643&f[]=848&f[]=808&f[]=630&f[]=1664' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		$what = rawurlencode(self::fromUTF(rawurldecode($what),"CP1251"));
		$cli = $this->fetch( $url.'/forum/tracker.php' ); // just for login
		$cli = $this->fetch( $url.'/forum/tracker.php', 0, "POST", "application/x-www-form-urlencoded",
			'prev_my=0&prev_new=0&prev_oop=1'.$cat.'&o=10&s=2&oop=1&nm='.$what.'&submit=%CF%EE%E8%F1%EA' );
		if(($cli!==false) && $this->parseTList($cli->results,$added,$ret,$limit))
		{
			$res = preg_match_all('/<a class="pg" href="tracker.php\?search_id=(?P<next>[^"]*)">/siU', $cli->results, $next);
			$next = array_unique($next["next"]);
			for($pg = 0; $pg<count($next); $pg++)
			{
				$cli = $this->fetch( $url.'/forum/tracker.php?search_id='.self::removeTags($next[$pg]) );
				if(($cli==false) || !$this->parseTList($cli->results,$added,$ret,$limit))
					break;
			}
		}
	}
}
