<?php

class MininovaEngine extends commonEngine
{
	public $defaults = array( "public"=>true, "page_size"=>200 );
	public $categories = array( 'all'=>'0', 'Anime'=>1, 'Books'=>'2', 'Games'=>'3', 'Movies'=>'4', 'Music'=>5,
		'Other'=>9, 'Pictures'=>'6', 'Software'=>'7', 'TV Shows'=>'8', 'ViewCave'=>'11' );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://www.mininova.org';
		if($useGlobalCats)
			$categories = array( 'all'=>'0', 'movies'=>'4', 'tv'=>'8', 'music'=>'5', 'games'=>'3', 'anime'=>'1', 'software'=>'7', 'pictures'=>'6', 'books'=>'2' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];

		for($pg = 1; $pg<11; $pg++)
		{
			$itemsOnPage = 0;
			$cli = $this->fetch( $url.'/search/'.$what.'/'.$cat.'/seeds/'.$pg );
			if( ($cli==false) || (strpos($cli->results, "<h1>No results for")!==false) )
				break;
			$result = $cli->results;
			$first = strpos($result, "<table>");
			$last = strpos($result, "</table>");
			if(($first!==false) && ($last!==false))
				$result = substr($result,$first,$last);
			$res = preg_match_all("'<td(| .*?)>(.*?)</td>'si", $result, $items);
			if(($res!==false) && ($res>0))
			{
				for( $i=0; $i<count($items[2]); $i+=6)
				{
                                        if(preg_match( "`<a href=\"/tor/(?P<id>\d+)[^\"]*\">(?P<name>.*)</a>`si", $items[2][$i+2], $matches )==1)
					{
						$link = $url."/get/".$matches["id"];
						$itemsOnPage++;
						if(!array_key_exists($link,$ret))
						{
							$item = $this->getNewEntry();
							$item["time"] = strtotime(self::removeTags($items[2][$i]));
							$item["cat"] = self::removeTags($items[2][$i+1]);
							$item["desc"] = $url."/tor/".$matches["id"];
							$item["name"] = self::removeTags($matches["name"]);
							$item["size"] = self::formatSize($items[2][$i+3]);
							$item["seeds"] = intval(self::removeTags($items[2][$i+4]));
							$item["peers"] = intval(self::removeTags($items[2][$i+5]));
							$ret[$link] = $item;
							$added++;
							if($added>=$limit)
								return;
						}
					}
				}
			}
			if(!$itemsOnPage)
				return;
		}
	}
}

?>