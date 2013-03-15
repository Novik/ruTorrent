<?php

class PtPEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>50, "cookies"=>"passthepopcorn.me|session=XXX" );
	public $categories = array
	( 
		'all'=>'', 
		'Feature Film'=>'&filter_cat[1]=1', 
		'Short Film'=>'&filter_cat[2]=1', 
		'Miniseries'=>'&filter_cat[3]=1', 
		'Stand-up Comedy'=>'&filter_cat[4]=1', 
		'Concert'=>'&filter_cat[5]=1' ,
	);

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://passthepopcorn.me';
		if($useGlobalCats)
			$categories = array( 'all'=>'' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];

		for($pg = 1; $pg<11; $pg++)
		{
			$itemsFound = false;
			$cli = $this->fetch( $url.'/torrents.php?searchstr='.$what.$cat.'&order_by=seeders&grouping=1&page='.$pg );			
			if( ($cli==false) || (strpos($cli->results, "<h2>Your search did not match anything.</h2>")!==false) ||
				(strpos($cli->results, "<td>Password&nbsp;</td>")!==false))
				break;
		
			$res = preg_match_all('`<tr class="group">.*'.
				'<td class="small" id="large_groupid_(?P<id>\d+)".*'.
				'title="View Torrent">(?P<name>.*)<span'.
				'`siU', $cli->results, $matches);

			if($res)
			{
				$groups = array();
                                for($i=0; $i<$res; $i++)
					$groups[intval($matches["id"][$i])] = self::removeTags($matches["name"][$i]);

				$res = preg_match_all('`<tr class="group_torrent groupid_(?P<id>\d+)[ "].*'.
					'\[<a href="torrents\.php\?(?P<link>.*)" title="Download">DL</a>.*'.
					'<a href="torrents\.php\?id=(?P<desc>.*)">(?P<name>.*)</a>.*'.
					'<td class="nobr"><span class="time" title="(?P<date>.*)">.*</span></td>.*'.
					'<td class="nobr">(?P<size>.*)</td>.*'.
					'<td.*>.*</td>.*<td.*>(?P<seeds>.*)</td>.*'.
					'<td.*>(?P<leech>.*)</td>'.
					'`siU', $cli->results, $matches);

				if($res)
				{
					$itemsFound = true;
					for($i=0; $i<$res; $i++)
					{
						$link = $url."/torrents.php?".self::removeTags($matches["link"][$i]);
						if(!array_key_exists($link,$ret))
						{
							$item = $this->getNewEntry();
							$item["desc"] = $url."/torrents.php?id=".self::removeTags($matches["desc"][$i]);
							$item["size"] = self::formatSize($matches["size"][$i]);
							$item["time"] = strtotime(self::removeTags($matches["date"][$i]));
							$item["seeds"] = intval(self::removeTags($matches["seeds"][$i]));
							$item["peers"] = intval(self::removeTags($matches["leech"][$i]));
							$grp = intval($matches["id"][$i]);
							if(array_key_exists($grp,$groups))
								$item["name"] = $groups[$grp].self::removeTags($matches["name"][$i]);
							else
								$item["name"] = self::removeTags($matches["name"][$i]);

							$ret[$link] = $item;
							$added++;
							if($added>=$limit)
								return;
						}
					}
				}
			}
			else
			{
				$res = preg_match_all('`<a href="torrents\.php\?(?P<link>[^"]*)" title="Download">DL</a>.*'.
					'<a href="torrents\.php\?id=(?P<desc>.*)" title="Permalink">.*'.
					'\); return false;">(?P<name>.*)</a>.*'.
					'<td class="nobr">(?P<size>.*)</td>.*'.
					'<td.*>.*</td>.*<td.*>(?P<seeds>.*)</td>.*'.
					'<td.*>(?P<leech>.*)</td>.*'.
                                        '<span class="time" title="(?P<date>.*)">'.
					'`siU', $cli->results, $matches);
				if($res)
				{
					$title = '';
					if( preg_match( '`<title>(?P<title>.*)::`',$cli->results, $matches1 ) )
						$title = $matches1["title"];
					for($i=0; $i<$res; $i++)
					{
						$link = $url."/torrents.php?".self::removeTags($matches["link"][$i]);
						if(!array_key_exists($link,$ret))
						{
							$item = $this->getNewEntry();
							$item["desc"] = $url."/torrents.php?id=".self::removeTags($matches["desc"][$i]);
							$item["size"] = self::formatSize($matches["size"][$i]);
							$item["seeds"] = intval(self::removeTags($matches["seeds"][$i]));
							$item["peers"] = intval(self::removeTags($matches["leech"][$i]));
							$item["name"] = self::removeTags($title.' '.$matches["name"][$i]);
							$ret[$link] = $item;
							$added++;
							if($added>=$limit)
								return;
						}
					}
				}
			}

			if(!$itemsFound)
				break;
		}
	}
}
