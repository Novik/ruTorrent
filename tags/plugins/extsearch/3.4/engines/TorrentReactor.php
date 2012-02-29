<?php

class TorrentReactorEngine extends commonEngine
{
	public $defaults = array( "public"=>true, "page_size"=>35 );
	public $categories = array( 'all'=>'', 'Anime'=>'1', 'Software'=>'2', 'Games'=>'3', 'Movies'=>'5', 'Music'=>'6', 
		'Other'=>'7', 'Series/TV Shows'=>'8' );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://www.torrentreactor.net';
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
			$cli = $this->fetch( $url.'/search.php?search=&words='.$what.'&cid='.$cat.'&type=1&orderby=a.seeds&asc=0&skip='.($pg*35) );
			if($cli==false || (strpos($cli->results, '<td colspan="8" bgcolor="#ffffff">No results.')!==false))
				break;
			$res = preg_match_all('/<tr class="torrent_.*">.*<a rel="nofollow" href="(?P<link>[^"]*torrentreactor.net\/download.php\?[^"]*)">.*'.
				'<a href="\/torrents\/(?P<desc>.*)">(?P<name>.*)<\/a><\/td>.*<td>(?P<size>.*)<\/td>.*<td>(?P<seeds>.*)<\/td>.*<td>(?P<leech>.*)<\/td>.*'.
				'<td><a title=".*">(?P<cat>.*)<\/a><\/td>/siU', $cli->results, $matches);
			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = self::removeTags($matches["link"][$i]);
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["desc"] = $url."/torrents/".$matches["desc"][$i];
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize(trim($matches["size"][$i]));
						$item["seeds"] = intval(self::removeTags($matches["seeds"][$i]));
						$item["peers"] = intval(self::removeTags($matches["leech"][$i]));
						$item["cat"] = self::removeTags(trim($matches["cat"][$i]));
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