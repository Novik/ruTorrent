<?php

class VertorEngine extends commonEngine
{
	public $defaults = array( "public"=>true, "page_size"=>30 );
	public $categories = array( 'all'=>'', 'Anime'=>'1', 'Software'=>'2', 'Games'=>'3', 'Movies'=>'5', 'Music'=>'6', 
		'Other'=>'7', 'Series/TV Shows'=>'8' );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://www.vertor.com';
		if($useGlobalCats)
			$categories = array( 'all'=>'', 'movies'=>'5', 'tv'=>'8', 'music'=>'6', 'games'=>'3', 'anime'=>'1', 'software'=>'2' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];

		for($pg = 0; $pg<10; $pg++)
		{
			
			$cli = $this->fetch( $url.'/torrent-search/?words='.$what.'&cid='.$cat.'&type=1&exclude=&hash=&new=0&orderby=a.seeds&asc=0&p='.$pg );
			if($cli==false || (strpos($cli->results, '>Nothing found</td>')!==false))
				break;
			$result = $cli->results;
			$pos = strpos($result, "<h2>Vertor search results</h2>");
			if($pos!==false)
				$result = substr($result,$pos);
				
			$res = preg_match_all('`<td class="first" >.*<a title="View information[^"]*" href="/torrents/(?P<id>\d*)/(?P<desc>[^"]*)">(?P<name>.*)</a>'.
				'<span class="quick">In&nbsp;(?P<cat>[^<]*)</span>.*'.
				'</td>.*<td>(?P<date>.*)</td>.*'.
				'<td>(?P<size>.*)</td>.*'.
				'<td>.*</td>.*'.
				'<td class="s">(?P<seeds>\d*)</td>.*'.
				'<td class="l">(?P<leech>\d*)</td>'.
				'`siU', $result, $matches);

			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $url."/index.php?mod=download&id=".$matches["id"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["desc"] = $url.'/torrents/'.$matches["id"][$i].'/'.self::removeTags($matches["desc"][$i]);
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize(trim($matches["size"][$i]));
						$item["seeds"] = intval(self::removeTags($matches["seeds"][$i]));
						$item["peers"] = intval(self::removeTags($matches["leech"][$i]));
						$item["cat"] = self::removeTags(trim($matches["cat"][$i]));
						$item["time"] = strtotime(trim(self::removeTags($matches["date"][$i])));
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
