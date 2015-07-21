<?php

class AnimeBytesEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>50, "auth"=>1 );

	public $categories = array
	(
		'all'=>'',
		'Anime'=>'&filter_cat[1]=1',
		'Printed Media'=>'&filter_cat[2]=1',
		'Games'=>'&filter_cat[3]=1',
		'Live Action'=>'&filter_cat[4]=1',
		'Packs'=>'&filter_cat[5]=1'
	);



	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://animebytes.tv';

		if($useGlobalCats)
			$categories = array( 'all'=>'', 'anime'=>'&filter_cat[1]=1', 'books'=>'&filter_cat[2]=1', 'games'=>'&filter_cat[3]=1', 'live action'=>'&filter_cat[4]=1', 'packs'=>'&filter_cat[5]=1' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];


		for($pg = 1; $pg<10; $pg++)
		{
			$itemsFound = false;
                        $cli = $this->fetch( $url.'/torrents.php?searchstr='.$what.'&tags_type=1&order_by=time_added&way=desc&page='.$pg.$cat );

			if( ($cli==false) || (strpos($cli->results, "<h2>Your search did not match anything.</h2>")!==false) ||
				(strpos($cli->results, "<td>Password&nbsp;</td>")!==false))
				break;

			$res = preg_match_all('/series.php\?id=(?P<desc>\d+)".*>(?P<name>.*)<\/a>.*'.
				'\[<a href="torrents.php\?(?P<link>.*)" title="Download">DL<\/a>.*'.
				'title="View Torrent">(?P<cat>.*)<\/a>.*'.
				'<td class="torrent_size"><span>(?P<size>.*)<\/span>.*'.
				'title="Seeders"><span>(?P<seeds>\d+)<\/span>.*'.
                'title="Leechers"><span>(?P<leech>\d+)<\/span>.*'.
                '/siU', $cli->results, $matches);



			if($res)
			{
				$itemsFound = true;
				for($i=0; $i<$res; $i++)
				{
					$link = $url."/torrents.php?".self::removeTags($matches["link"][$i]);
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/series.php?id=".$matches["desc"][$i];
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

                        if(!$itemsFound)
                             break;
                }
        }
}
