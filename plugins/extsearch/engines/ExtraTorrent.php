<?php

class ExtraTorrentEngine extends commonEngine
{
	public $defaults = array( "public"=>true, "page_size"=>50 );
	public $categories = array( 'all'=>'', 'Movies'=>'4', 'TV'=>'8', 'Music'=>'5',
		'Adult/Porn'=>'533', 'Software'=>'7', 'Games'=>'3', 'Anime'=>'1', 'Books'=>'2', 'Pictures'=>'6', 'iPod'=>'416', 'Other'=>'9' );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://extratorrent.cc';
		if($useGlobalCats)
			$categories = array( 'all'=>'', 'movies'=>'4', 'tv'=>'8', 'music'=>'5', 'games'=>'3', 'anime'=>'1', 'software'=>'7', 'pictures'=>'6', 'books'=>'2' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];

		for($pg = 1; $pg<11; $pg++)
		{
			$cli = $this->fetch( $url.'/advanced_search/?page='.$pg.'&with='.$what.'&seeds_from=1&srt=seeds&order=desc&pp=50&s_cat='.$cat.'&size_to=#results' );

			if(($cli==false) || (strpos($cli->results, "<i>No torrents</i>")!==false))
				break;

			$res = preg_match_all('`<tr class="tl.*"><td><a href="/torrent_download/.*" title="Download .*">'.
				'<img src=".*/></a></td><td>'.
				'<a href="/category/.*" title="Browse (?P<cat>.*)">.*'.
				'<a href="/torrent/(?P<id>\d*)/[^#]*">(?P<name>.*)</a><span class="c_tor"> in .*'.
				'</td><td>(?P<size>.*)</td><td class="s.">(?P<seeds>.*)</td>'.
				'<td class="l.">(?P<leech>.*)</td>'.
				'`siU', $cli->results, $matches );

			if($res)
			{
				for( $i=0; $i<$res; $i++)
				{
					$link = $url."/download/".$matches["id"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["desc"] = $url."/torrent/".$matches["id"][$i];
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["size"] = self::formatSize($matches["size"][$i]);
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
