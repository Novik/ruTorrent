<?php

class BroadcasTheEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>50 );
	public $categories = array( 'all'=>'' );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://broadcasthe.net';

		for($pg = 1; $pg<11; $pg++)
		{
			$cli = $this->fetch( $url.'/torrents.php?searchstr='.$what.'&searchtags=&tags_type=0&order_by=s6&order_way=desc&page='.$pg );
			if( ($cli==false) || (strpos($cli->results, "<h2>Your search was way too l33t, try dumbing it down a bit.</h2>")!==false)
				|| (strpos($cli->results, '<form name="loginform" id="loginform" method="post"')!==false))
				break;

			$res = preg_match_all('/<tr class="torrent">.*<td class="center"><img src=".*" alt="(?P<cat>.*)".*<\/td>.*'.
				'\[<a href="torrents.php\?action=download(?P<link>.*)" title="Download">.*'.
                		'<a href="series.php\?id=.*>(?P<name1>.*)<\/a> - <a href="torrents.php\?id=(?P<desc>.*)" title="View Torrent">(?P<name2>.*)<\/a><br \/>.*'.
		             	'<\/a> - <b>Added:<\/b>(?P<date>.*)<\/div>.*'.
				'<td>.*<\/td>.*'.
				'<td class="nobr">(?P<size>.*)<\/td>.*'.
				'<td>(?P<seeds>.*)<\/td>.*'.
				'<td>(?P<leech>.*)<\/td>/siU', $cli->results, $matches);

			if(($res!==false) && ($res>0) &&
				count($matches["link"])==count($matches["cat"]) &&
				count($matches["cat"])==count($matches["name1"]) && 
				count($matches["name1"])==count($matches["name2"]) &&
				count($matches["name2"])==count($matches["desc"]) &&
				count($matches["desc"])==count($matches["date"]) &&
				count($matches["date"])==count($matches["leech"]) &&
				count($matches["seeds"])==count($matches["leech"]))
			{
				for($i=0; $i<count($matches["link"]); $i++)
				{

					$link = $url."/torrents.php?action=download".self::removeTags($matches["link"][$i]);
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/torrents.php?id=".self::removeTags($matches["desc"][$i]);
						$item["name"] = self::removeTags($matches["name1"][$i]." - ".$matches["name2"][$i]);
						$item["size"] = self::formatSize($matches["size"][$i]);
						$item["time"] = strtotime(trim(self::removeTags($matches["date"][$i])));
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

?>