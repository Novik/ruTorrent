<?php

class TorrentLeechEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>25, "cookies"=>"www.torrentleech.org|pass=XXX;uid=XXX" );
	public $categories = array( 'all'=>'&cat=0', 'Anime/Cartoon' => "&cat=27", 'Appz' => "&cat=39&cat=22&cat=1&cat=32",
		'Books' => "&cat=28", 'Documentaries' => "&cat=40", 'Episodes' => "&cat=33&cat=7",
		'Games' => "&cat=4&cat=41&cat=21&cat=17&cat=45&cat=26&cat=36&cat=44&cat=24&cat=10",
		'Movies' => "&cat=20&cat=43&cat=35&cat=38&cat=42&cat=19", 'Music' => "&cat=6",
		'Nintendo DS' => "&cat=11", 'NonScene' => "&cat=47&cat=46&cat=48" );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://www.torrentleech.org';
		if($useGlobalCats)
			$categories = array( 'all'=>'&cat=0', 'movies'=>'&cat=40&cat=20&cat=43&cat=35&cat=38&cat=42&cat=19&cat=47&cat=46&cat=48', 
				'tv'=>'&cat=33&cat=7', 'music'=>'&cat=6', 'games'=>'&cat=4&cat=41&cat=21&cat=17&cat=45&cat=26&cat=36&cat=44&cat=24&cat=10', 
				'anime'=>'&cat=27', 'software'=>'&cat=39&cat=22&cat=1&cat=32', 'books'=>'&cat=28' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		for($pg = 0; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/browse.php?search='.$what.'&order=seeddesc&page='.$pg.$cat );
			if( ($cli==false) || (strpos($cli->results, "<h2>Nothing Found!</h2>")!==false))
				break;
			$res = preg_match_all('/<img border="0" src=.* alt="(?P<cat>.*)" \/><\/a>'.

				'.*<a href="details.php\?id=(?P<id>\d+)&amp;hit=1".*>(?P<name>.*)<\/a><br><font.*>(?P<date>.*)<\/font>.*'.
				'<td.*>.*href="download.php\/\d+\/(?P<tname>.*)">.*'.
				'<td .*>.*<\/td>.*'.
				'<td .*>(?P<size>.*)<\/td>'.
				'.*<td .*>.*<\/td>.*<td .*>(?P<seeds>.*)<\/td>.*<td .*>(?P<leech>.*)<\/td>/siU', $cli->results, $matches);
			if(($res!==false) && ($res>0) &&
				count($matches["id"])==count($matches["cat"]) &&
				count($matches["cat"])==count($matches["name"]) && 
				count($matches["name"])==count($matches["size"]) &&
				count($matches["size"])==count($matches["seeds"]) &&
				count($matches["seeds"])==count($matches["date"]) &&
				count($matches["seeds"])==count($matches["tname"]) &&
				count($matches["date"])==count($matches["leech"]) )
			{
				for($i=0; $i<count($matches["id"]); $i++)
				{
					$link = $url."/download.php/".$matches["id"][$i]."/".$matches["tname"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/details.php?id=".$matches["id"][$i].'&hit=1';
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize(str_replace("<br>"," ",$matches["size"][$i]));
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

?>