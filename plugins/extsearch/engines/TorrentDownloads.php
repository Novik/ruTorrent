<?php

class TorrentDownloadsEngine extends commonEngine
{
	public $defaults = array( "public"=>true, "page_size"=>50 );
	public $categories = array( 'all'=>'0', 'Anime'=>'1', 'Books'=>'2', 'Games'=>'3',
		'Movies'=>'4', 'Music'=>'5', 'Software'=>'7', 'TV Shows'=>'8', 'Other'=>'9' );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://www.torrentdownloads.net';
		if($useGlobalCats)
			$categories = array( 'all'=>'0', 'movies'=>'4', 'tv'=>'8', 'music'=>'5', 'games'=>'3', 'anime'=>'1', 'software'=>'7', 'books'=>'2' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];

		for($pg = 1; $pg<11; $pg++)
		{
			$cli = $this->fetch( $url.'/search/?page='.$pg.'&search='.$what.'&s_cat='.$cat.'&srt=seeds&order=desc' );
			if( ($cli==false) || (strpos($cli->results, "</ul>No torrents</div>")!==false) )
				break;
			$res = preg_match_all('/<a href="http:\/\/www.torrentdownloads.net\/torrent\/(?P<id>.*)\/.*">(?P<name>.*)<\/a><\/li><li>(?P<size>.*)<\/li><li>(?P<seeds>.*)<\/li><li>(?P<leech>.*)<\/li>/siU', $cli->results, $matches );
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

?>