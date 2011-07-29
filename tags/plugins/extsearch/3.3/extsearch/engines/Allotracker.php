<?php

class AllotrackerEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>50, "cookies"=>"www.allotracker.com|AT_auth=XXX;AT_key=XXX" );
	public $categories = array( 'all'=>'&cat=0', 'Anime' => '&cat=71', 'Appz' => "&cat=53&cat=52&cat=51",
		'E-Book' => '&cat=64', 'Games' => "&cat=65&cat=69&cat=66&cat=75&cat=54",'Mac' => '&cat=2', 'Movies' => "&cat=20&cat=44&cat=3&cat=10&cat=19",
		'Music' => "&cat=6&cat=46&cat=29", 'TV' => "&cat=43&cat=45&cat=42&cat=7", 'XXX' => "&cat=9&cat=47&cat=48" );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://www.allotracker.com';
		if($useGlobalCats)
			$categories = array( 'all'=>'&cat=0', 'movies'=>'&cat=20&cat=44&cat=3&cat=10&cat=19', 
				'tv'=>'&cat=43&cat=45&cat=42&cat=7', 'music'=>'&cat=6&cat=46&cat=29', 
				'games'=>'&cat=4&cat=21&cat=17&cat=16&cat=40&cat=39', 
				'anime'=>'&cat=23', 'software'=>'&cat=22&cat=1', 'books'=>'&cat=36' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		for($pg = 0; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/torrents-search.php?search='.$what.'&sort=id&order=desc&page='.$pg.$cat );
			if( ($cli===false) || (strpos($cli->results, "<B>'Aucun torrent trouv�, bas� sur vos crit�res de recherche.'</B>")!==false)
				|| (strpos($cli->results, '<form method=post action=account-login.php>')!==false))
				break;

			$res = preg_match_all('/<img border="0"src=.* alt="(?P<cat>.*)" \/><\/a><\/td>.*'.
				'href="torrents-details\.php\?id=(?P<id>\d+)&amp;hit=1">'.
				'<b>(?P<name>.*)<\/b><\/a><td .*><a href="download\.php\?id=.*&name=(?P<tname>.*)"><img .*><\/a><\/td><td .*>(?P<size>.*)<\/td>.*'.
				'<td .*>.*<\/td>.*'.
				'<td .*><b><font color=green><B>(?P<seeds>.*)<\/b><\/font><\/td>.*'.
				'<td .*><font color=red><B>(?P<leech>.*)<\/b><\/font><\/td>.*'.
				'<td .*><img .*><\/td>.*'.
				'<\/tr>/siU', $cli->results, $matches);
			if(($res!==false) && ($res>0) &&
				count($matches["id"])==count($matches["cat"]) &&
				count($matches["cat"])==count($matches["name"]) && 
				count($matches["name"])==count($matches["size"]) &&
				count($matches["size"])==count($matches["seeds"]) &&
				count($matches["seeds"])==count($matches["leech"]) &&
				count($matches["leech"])==count($matches["tname"]) )
			{
				for($i=0; $i<count($matches["id"]); $i++)
				{
					$link = $url."/download.php?id=".$matches["id"][$i]."&name=".$matches["tname"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/torrents-details.php?id=".$matches["id"][$i].'&hit=1';
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize(str_replace("<br>"," ",$matches["size"][$i]));
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

?>