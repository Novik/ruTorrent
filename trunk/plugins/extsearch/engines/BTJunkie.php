<?php

class BTJunkieEngine extends commonEngine
{
	public $defaults = array( "public"=>true, "page_size"=>25 );
	public $categories = array( 'all'=>'0', 'Audio'=>'1', 'Anime'=>'7', 'Games'=>'2', 'Software'=>'3', 'TV'=>'4', 
		'Unsorted'=>'5', 'Video'=>'6', 'XXX'=>'8' );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://btjunkie.org';
		if($useGlobalCats)
			$categories = array( 'all'=>'', 'movies'=>'6', 'tv'=>'4', 'music'=>'1', 'games'=>'2', 'anime'=>'7', 'software'=>'3' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];

		for($pg = 1; $pg<11; $pg++)
		{
			$cli = $this->fetch( $url.'/search?q='.$what.'&c='.$cat.'&p='.$pg.'&t=1&o=52&s=1' );
			if($cli==false || 
				(strpos($cli->results, "<strong>0 matches</strong>")!==false) ||
				(strpos($cli->results, "<b>Did you mean:</b></font")!==false))
				break;

			$res = preg_match_all('/<a href="(?P<link>[^"]*)" rel="nofollow"><img src="\/images\/down.gif" alt="Download Torrent" border="0"><\/a>.*'.
				'<a href="\/torrent\/.*;">.*<img name=".*alt="File Listing" border="0"><\/a>&nbsp;.*'.
				'<a href="\/torrent\/(?P<desc>.*)".*>(?P<name>.*)<\/a><\/th>.*'.
				'<a class="LightOrange" href="\/browse\/.*"><b>(?P<cat>.*)<\/b><\/a><\/th>.*'.
				'<th width="10%" align="center"><font color="#808080" style="font-weight: bold;">(?P<size>.*)<\/font><\/th>.*'.
				'<th .*>.*<\/font><\/th>.*'.
				'<th width="5%" align="center">(?P<seeds>.*)<\/th>.*'.
				'<th width="5%" align="center">(?P<leech>.*)<\/th>/siU', $cli->results, $matches);
			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $matches["link"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["desc"] = $url.'/torrent/'.$matches["desc"][$i];
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize(substr($matches["size"][$i],0,-2)." MB");
						$item["seeds"] = intval(self::removeTags($matches["seeds"][$i]));
						$item["peers"] = intval(self::removeTags($matches["leech"][$i]));
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