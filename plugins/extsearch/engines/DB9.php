<?php

class DB9Engine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>50, "auth"=>1 );
	public $categories = array( 'all'=>'', 'Music'=>'&filter_cat[1]=1', 'Tutorials'=>'&filter_cat[2]=1', 'Samples'=>'&filter_cat[3]=1', 
		'Videos'=>'&filter_cat[4]=1' );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://www.deepbassnine.com';
		if($useGlobalCats)
			$categories = array( 'all'=>'', 'Music'=>'&filter_cat[1]=1', 'Tutorials'=>'&filter_cat[2]=1', 'Samples'=>'&filter_cat[3]=1', 'Videos'=>'&filter_cat[4]=1' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];

		for($pg = 1; $pg<10; $pg++)
		{
			$itemsFound = false;
			$cli = $this->fetch( $url.'/torrents.php?searchstr='.$what.'&tags_type=1&order_by=seeders&order_way=desc&page='.$pg.$cat );
			if( ($cli==false) || (strpos($cli->results, "<h2>Your search did not match anything.</h2>")!==false) ||
				(strpos($cli->results, "<td>Password&nbsp;</td>")!==false))
				break;


			$res = preg_match_all('/<a href="artist.php\?id=.*">(?P<artist>.*)<\/a>.*'.
				'<a href="torrents.php\?id=(?P<desc>\d+)".*">(?P<name>.*)<\/a>.*'.
				'<a id="download" href="torrents.php\?(?P<link>.*)" title="Download"><\/a>.*'.
				'<td class="nobr"><span class="time" title="(?P<date>.*)">.*<\/span><\/td>.*'.
				'<td class="nobr">(?P<size>.*)<\/td>.*'.
				'<\/div>.*<div title="(?P<cat>.*)" class="cats_music.*'.
				'<td>(\d+)<\/td>.*<td>(?P<seeds>.*)<\/td>.*<td>(?P<leech>.*)<\/td>'.
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
						$item["desc"] = $url."/torrents.php?id=".$matches["desc"][$i];
						$item["name"] = self::removeTags($matches["artist"][$i])." - ".self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize($matches["size"][$i]);
						$item["time"] = strtotime(self::removeTags($matches["date"][$i]));
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
