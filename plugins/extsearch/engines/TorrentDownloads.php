<?php

class TorrentDownloadsEngine extends commonEngine
{
	public $defaults = array( "public"=>true, "page_size"=>50 );
	public $categories = array( 'all'=>'0', 'Anime'=>'1', 'Books'=>'2', 'Games'=>'3',
		'Movies'=>'4', 'Music'=>'5', 'Software'=>'7', 'TV Shows'=>'8', 'Other'=>'9' );

	protected static function getInnerCategory($cat)
	{
		$categories = array(
			'1'=>'Anime', 
			'2'=>'Books', 
			'3'=>'Games',
			'4'=>'Movies', 
			'5'=>'Music', 
			'7'=>'Software', 
			'8'=>'TV Shows', 
			'9'=>'Other');
		return(array_key_exists($cat,$categories) ? $categories[$cat] : '');
	}

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://www.torrentdownloads.me';
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
		
			$res = preg_match_all('`<img src="/templates/new/images/icons/menu_icon(?P<cat>\d+)\.png" alt="">'.
				'<a href="/torrent/(?P<id>.*)/.*"[^>]*>(?P<name>.*)</a>.*'.
				'<span>(?P<leech>.*)</span><span>(?P<seeds>.*)</span><span>(?P<size>.*)</span>'.
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
						$item["cat"] = self::getInnerCategory($matches["cat"][$i]);
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize($matches["size"][$i]);
						$item["seeds"] = intval(self::removeTags($matches["seeds"][$i]));
						$item["peers"] = intval(self::removeTags($matches["leech"][$i]));
						if(!$item["seeds"] && !$item["peers"])
							continue;
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
