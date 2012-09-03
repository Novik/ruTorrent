<?php

class TorrentZapEngine extends commonEngine
{
	public $defaults = array( "public"=>true, "page_size"=>50 );
	public $categories = array( 'all'=>'', 'Anime'=>'1', 'Books'=>'2', 'Games'=>'3',
		'Movies'=>'4', 'Music'=>'5', 'Pictures'=>'6', 'Software'=>'7', 'TV Shows'=>'8', 'Other'=>'9' );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://www.torrentzap.com';
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
			$cli = $this->fetch( $url.'/search.php?type=&q='.$what.'&sort=seeds&pg='.$pg.'&cats='.$cat );
			if($cli==false)
				break;

			$res = preg_match_all('`<td class="first">(?P<date>.*)</td>.*'.
				'<a href="/torrent/(?P<desc>.*)">(?P<name>.*)</a>.*'.
				'&url=(?P<link>.*)".*'.
				'<td>(?P<size>.*)</span></td>.*'.
				'<td class="s">(?P<seeds>.*)</td><td class="l">(?P<leech>.*)</td>'.
				'`siU', $cli->results, $matches );

			if($res)
			{
				for( $i=0; $i<$res; $i++)
				{
					$link = $matches["link"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["desc"] = $url."/torrent/".$matches["desc"][$i];
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize(str_replace('<span id="size">'," ",$matches["size"][$i]));
						$item["seeds"] = intval(self::removeTags($matches["seeds"][$i]));
						$item["peers"] = intval(self::removeTags($matches["leech"][$i]));
						$item["time"] = strtotime(self::removeTags($matches["date"][$i]));
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
