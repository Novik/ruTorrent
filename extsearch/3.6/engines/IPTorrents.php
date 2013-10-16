<?php

class IPTorrentsEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>35, "cookies"=>"www.iptorrents.com|pass=XXX;uid=XXX" );
	public $categories = array( 'all'=>'&l72=1&l73=1&l74=1&l75=1&l76=1',
		'Movies'=>'&l72=1', 'TV'=>'&l73=1', 'Games'=>'&l74=1', 'Music'=>'&l75=1', 'Books'=>'&l35=1', 'Anime'=>'&l60=1', 
		'Appz/misc'=>'&l1=1', 'Mac'=>'&l69=1', 'Mobile'=>'&l58=1', 'Pics/Wallpapers'=>'&l36=1', 'Sports'=>'&l55=1', 'XXX'=>'&l8=1' );


	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://www.iptorrents.com';
		if($useGlobalCats)
			$categories = array( 'all'=>'&l72=1&l73=1&l74=1&l75=1&l76=1', 
				'movies'=>'&l72=1', 'tv'=>'&l73=1', 'music'=>'&l75=1', 'games'=>'&l74=1', 
				'anime'=>'&l60=1', 'software'=>'&l1=1', 'pictures'=>'&l36=1', 'books'=>'&l35=1&l64=1' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		for($pg = 1; $pg<11; $pg++)
		{
			$cli = $this->fetch( $url.'/torrents/?'.$cat.'o=seeders;q='.$what.';qf=ti;p='.$pg );
			if( ($cli==false) || (strpos($cli->results, ">Nothing found!<")!==false) ||
				(strpos($cli->results, ">Password:<")!==false))
				break;
				
			$res = preg_match_all('`<img class=".*" width="50" src=.* alt="(?P<cat>.*)"></a>.*'.
				' href="/details\.php\?id=(?P<id>\d+)">(?P<name>.*)</a>.*'.
				't_ctime">(?P<date>.*)</div>.*'.
				'<td .*>.*href="/download\.php/\d+\/(?P<tname>.*)".*</a></td>'.
				'<td .*>.*</td><td .*>(?P<size>.*)</td><td .*>.*</td>'.
				'<td class="ac t_seeders">(?P<seeds>.*)</td>'.
				'<td class="ac t_leechers">(?P<leech>.*)</td>'.
				'`siU', $cli->results, $matches);
			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $url."/download.php/".$matches["id"][$i]."/".$matches["tname"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/details.php?id=".$matches["id"][$i];
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize($matches["size"][$i]);
						$item["time"] = strtotime(self::removeTags($matches["date"][$i]));
						$item["seeds"] = intval(self::removeTags($matches["seeds"][$i]));
						$item["peers"] = intval(self::removeTags($matches["leech"][$i]));
						$ret[$link] = $item;
						$added++;
						if($added>=$limit)
							return;
					}
				}
			}
			else
				break;
		}
	}
}
