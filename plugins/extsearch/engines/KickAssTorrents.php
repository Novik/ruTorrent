<?php

class KickAssTorrentsEngine extends commonEngine
{
	public $defaults = array( "public"=>true, "page_size"=>25 );
	public $categories = array( 'all'=>'', 'Anime'=>' category:anime', 'Applications'=>' category:applications', 'Books'=>' category:books', 'Games'=>' category:games', 'Movies'=>' category:movies', 
		'Music'=>' category:music', 'Other'=>' category:other', 'TV'=>' category:tv', 'XXX'=>' category:xxx' );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://kickass.to';
		if($useGlobalCats)
			$categories = array( 'all'=>'', 'movies'=>' category:movies', 'tv'=>' category:tv', 'music'=>' category:music', 'games'=>' category:games', 'anime'=>' category:anime', 'software'=>' category:applications', 'books'=>' category:books' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];

		for($pg = 1; $pg<11; $pg++)
		{
			$cli = $this->fetch( $url.'/usearch/'.$what.$cat.'/'.$pg.'/?field=seeders&sorder=desc' );
			if( ($cli==false) || (strpos($cli->results, "<h2>Nothing found!</h2>")!==false) )
				break;
			$res = preg_match_all('`href="magnet:(?P<link>.*)".*<div class="torrentname">.*'.
				'<a href="(?P<desc>.*)" class="cellMainLink">(?P<name>.*)</a>.*'.
				'<span id="cat_\d+">(?P<cat>.*)</span>.*'.
				'<td class="nobr.*">(?P<size>.*)</td>.*'.
				'<td.*>.*</td>.*'.
				'<td.*>(?P<date>.*)</td>.*'.
				'<td class=".*">(?P<seeds>.*)</td>.*'.
				'<td class=".*">(?P<leech>.*)</td>'.
				'`siU', $cli->results, $matches);
			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = "magnet:".$matches["link"][$i];
					if(!array_key_exists($link,$ret) && intval($matches["seeds"][$i]))
					{
						$item = $this->getNewEntry();
						$item["desc"] = $url.$matches["desc"][$i];
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize($matches["size"][$i]);
						$item["seeds"] = intval($matches["seeds"][$i]);
						$item["peers"] = intval($matches["leech"][$i]);
						$item["time"] = strtotime(self::removeTags($matches["date"][$i]).' ago');
						$item["cat"] = self::removeTags($matches["cat"][$i]);
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
