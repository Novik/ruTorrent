<?php

class DemonoidEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>50, "cookies"=>"www.demonoid.com|uhsh=XXX;uid=XXX" );
	public $categories = array( 'all'=>'0', 'Anime'=>'9', 'Applications'=>'5', 'Audio Books'=>'17', 'Books'=>'11', 'Comics'=>'10',
		'Games'=>'4', 'Miscellaneous'=>'6', 'Movies'=>'1', 'Music'=>'2', 'Music Videos'=>'13', 'Pictures'=>'8', 'TV'=>'3' );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://www.demonoid.pw';

		if($useGlobalCats)
			$categories = array( 'all'=>'0', 'movies'=>'1', 'tv'=>'3', 'music'=>'2', 'games'=>'4', 'anime'=>'9', 'software'=>'5', 'pictures'=>'8', 'books'=>'11' );
		else
			$categories = &$this->categories;

		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];

		for($pg = 1; $pg<11; $pg++)
		{
			$cli = $this->fetch( $url.'/files/?subcategory=All&quality=All&seeded=0&external=2&uid=0&sort=S&query='.$what.'&category='.$cat.'&page='.$pg );
			
			if( ($cli==false) || (strpos($cli->results, "<b>No torrents found</b>")!==false)
				|| (strpos($cli->results, '>Password:</td>')!==false))
				break;
			$res = preg_match_all('/<td colspan="10" class="added_today">Added on (?P<date>.*)<\/td>(?P<item>.*)(<tr align="left" bgcolor="#CCCCCC">|<\!-- end torrent list -->)/siU', $cli->results, $items);
                        if(($res!==false) && ($res>0))
			{
				for($i=0; $i<count($items["date"]); $i++)
				{
					$res = preg_match_all('/<!-- tstart --><a href=".*"><img src=".*" height="30" alt="(?P<cat>.*)".*<\/a><!-- tend --><\/td>.*'.
						'<a href="\/files\/details\/(?P<id>.*)">(?P<name>.*)<\/a>.*<\/td>.*'.
						'<a href="\/files\/download\/.*<\/a><\/td>.*'.
						'<td .*>(?P<size>.*)<\/td>.*'.
						'<td .*>.*<\/td>.*'.
						'<td .*>.*<\/td>.*'.
						'<td .*>(?P<seeds>.*)<\/td>.*'.
						'<td .*>(?P<leech>.*)<\/td>/siU', $items["item"][$i], $matches);
                                        if(($res!==false) && ($res>0) &&
						count($matches["id"])==count($matches["cat"]) &&
						count($matches["cat"])==count($matches["name"]) && 
						count($matches["name"])==count($matches["size"]) &&
						count($matches["size"])==count($matches["seeds"]) &&
						count($matches["seeds"])==count($matches["leech"]) )
					{
						for($j=0; $j<count($matches["id"]); $j++)
						{
                					$link = $url."/files/download/".$matches["id"][$j];
							if(!array_key_exists($link,$ret))
							{
								$item = $this->getNewEntry();
								$item["cat"] = self::removeTags($matches["cat"][$j]);
								$item["desc"] = $url."/files/details/".$matches["id"][$j];
								$item["name"] = self::removeTags($matches["name"][$j]);
								$item["size"] = self::formatSize($matches["size"][$j]);
								$item["time"] = strtotime(self::removeTags($items["date"][$i]));
								$item["seeds"] = intval(self::removeTags($matches["seeds"][$j]));
								$item["peers"] = intval(self::removeTags($matches["leech"][$j]));
								$ret[$link] = $item;
								$added++;
								if($added>=$limit)
									return;
							}
						}
					}
					else
						return;
				}
			}
			else
				return;
		}
	}
}
