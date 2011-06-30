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
			$cli = $this->fetch( $url.'/search/'.$what.'/'.$pg.'/?categories[]='.$cat.'&field=seeders&sorder=desc&json=1' );
			if($cli==false || 
				!preg_match('/"total_results": (?P<cnt>\d+)/siU',$cli->results, $matches) ||
				(intval($matches["cnt"])<=0))
				break;
			$res = preg_match_all('/[\,\[]\{\n"title": "(?P<name>.*)",\n"category": "(?P<cat>.*)",\n"link": "(?P<desc>.*)",.*'.
				'"pubDate": "(?P<date>.*)",\n"torrentLink": "(?P<link>.*)",.*'.
				'"seeds": "(?P<seeds>.*)",\n"leechs": "(?P<leech>.*)",\n"size": "(?P<size>.*)",.*\}/siU', $cli->results, $matches);
			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $matches["link"][$i];
					if(!array_key_exists($link,$ret) && intval($matches["seeds"][$i]))
					{
						$item = $this->getNewEntry();
						$item["desc"] = $matches["desc"][$i];
						$item["name"] = self::fromJSON(self::removeTags($matches["name"][$i]));
						$item["size"] = $matches["size"][$i];
						$item["seeds"] = intval($matches["seeds"][$i]);
						$item["peers"] = intval($matches["leech"][$i]);
						$item["time"] = strtotime($matches["date"][$i]);
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