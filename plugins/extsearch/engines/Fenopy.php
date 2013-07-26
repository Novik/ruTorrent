<?php

class FenopyEngine extends commonEngine
{
       	public $defaults = array( "public"=>true, "page_size"=>50 );
	public $categories = array( 'all'=>'0', 'Animes'=>'5', 'Books'=>'7', 'Games'=>'4', 'Movies'=>'3', 
		'Music'=>'1', 'Others'=>'72', 'Apps'=>'6', 'TV Shows'=>'78', 'Videos'=>'2' ); 

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://unblockfenopy.eu';
		if($useGlobalCats)
			$categories = array( 'all'=>'0', 'movies'=>'3', 'tv'=>'78', 'music'=>'1', 'games'=>'4', 'anime'=>'5', 'software'=>'6', 'pictures'=>'72', 'books'=>'7' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		for($pg = 0; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/?keyword='.$what.'&inside=0&cat='.$cat.'&order=2&start='.$pg*50 );
			if( ($cli==false) || (strpos($cli->results, "<h2>No match found</h2>")!==false))
				break;
			$res = preg_match_all('`<a href="/torrent/(?P<desc>.*)" title="(?P<name>.*)">.*</a>'.
				'.*(?P<cat>.*)<.*'.
				'window\.location\.href=\'(?P<link>.*)\';.*'.
				'<td class="si">(?P<size>.*)</td>.*'.
				'<td class="se">(?P<seeds>.*)</td>.*'.
				'<td class="le">(?P<leech>.*)</td>.*'.
				'`siU', $cli->results, $matches);

			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $url.$matches["link"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/torrent/".$matches["desc"][$i];
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
