<?php

class KickAssTorrentsEngine extends commonEngine
{
	public $defaults = array( "public"=>true, "page_size"=>25 );
	public $categories = array( 'all'=>'', 'Anime'=>'anime', 'Applications'=>'applications', 'Books'=>'books', 'Games'=>'games', 'Movies'=>'movies', 
		'Music'=>'music', 'Other'=>'other', 'TV'=>'tv', 'XXX'=>'xxx' );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://www.kickasstorrents.com';
		if($useGlobalCats)
			$categories = array( 'all'=>'', 'movies'=>'movies', 'tv'=>'tv', 'music'=>'music', 'games'=>'games', 'anime'=>'anime', 'software'=>'applications', 'books'=>'books' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];

		for($pg = 1; $pg<11; $pg++)
		{
			$cli = $this->fetch( $url.'/new/?q='.$what.'&categories[0]='.$cat.'&page='.$pg.'&field=seeders&sorder=desc' );
			if($cli==false || (strpos($cli->results, "Nothing found!</h2>")!==false))
				break;

			$res = preg_match_all('/<div><a title="Download torrent file" href="(?P<link>.*)".*<div class="torrentname"><img.*\/> <a href="(?P<desc>.*)">(?P<name>.*)<\/a>.*'.
				'<span> in <span.*><a href=".*">(?P<cat>.*)<\/a>.*<td class="nobr">(?P<size>.*)<\/td>.*'.
				'<td>.*<\/td>.*<td>(?P<date>.*)<\/td>.*<td.*>(?P<seeds>.*)<\/td>.*<td.*>(?P<leech>.*)<\/td>/siU', $cli->results, $matches);

			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $matches["link"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["desc"] = $url.$matches["desc"][$i];
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize(trim($matches["size"][$i]));
						$item["seeds"] = intval(self::removeTags($matches["seeds"][$i]));
						$item["peers"] = intval(self::removeTags($matches["leech"][$i]));
						$item["time"] = strtotime(trim(self::removeTags($matches["date"][$i])));
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

?>