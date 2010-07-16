<?php

class VertorEngine extends commonEngine
{
	public $defaults = array( "public"=>true, "page_size"=>35 );
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
			$cli = $this->fetch( $url.'/index.php?words='.$what.'&exclude=&cid='.$cat.'&orderby=a.seeds&type=1&asc=0&mod=search&search=&x=0&y=0&p='.$pg );
			if($cli==false || (strpos($cli->results, '>Nothing found</td>')!==false))
				break;
			$res = preg_match_all('/<tr>.*<td class="center" style="text-align:right" nowrap="true">(?P<date>.*)<\/td>.*<a rel="nofollow" href="(?P<link>.*)".*'.
				'<a href="http:\/\/www.vertor.com\/index.php\?(?P<desc>.*)">(?P<name>.*)<\/a><\/td>.*<td class="center"><a href="http:\/\/www\.vertor\.com\/index\.php\?mod=group.*">(?P<cat>.*)<\/a><\/td>.*'.
				'<td class="center">(?P<size>.*)<\/td>.*<td .*>(?P<seeds>.*)<\/td>.*<td .*>(?P<leech>.*)<\/td>/siU', $cli->results, $matches);
			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = self::removeTags($matches["link"][$i]);
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["desc"] = $url."/index.php?".self::removeTags($matches["desc"][$i]);
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

?>