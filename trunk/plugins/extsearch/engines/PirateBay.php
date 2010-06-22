<?php

class PirateBayEngine extends commonEngine
{
	public function makeClient($url)
	{
		$client = commonEngine::makeClient($url);
		$client->cookies = array("lw"=>"s");
		return($client);
	}
	public function action($what,$cat,&$ret,$limit)
	{
		$added = 0;
		$url = 'http://thepiratebay.org';
		$categories = array( 'all'=>'0', 'movies'=>'200', 'tv'=>'205', 'music'=>'100', 'games'=>'400', 'anime'=>'0', 'software'=>'300', 'pictures'=>'603', 'books'=>'601' );
		if(!array_key_exists($cat,$categories))
			$cat = 'all';
		$maxPage = 10;
		for($pg = 0; $pg<$maxPage; $pg++)
		{
			$cli = $this->fetch( $url.'/search/'.$what.'/'.$pg.'/7/'.$categories[$cat] );
			if($cli==false || !preg_match('/<\/span>&nbsp;Displaying hits from \d+ to \d+ \(approx (?P<cnt>\d+) found\)/siU',$cli->results, $matches))
				break;
			$maxPage = ceil(intval($matches["cnt"])/30);
			$res = preg_match_all('/<td class="vertTh"><a href="\/browse.*>(?P<cat>.*)<\/a><\/td>.*'.
                                '<td><a href="\/torrent\/(?P<desc>.*)".*>(?P<name>.*)<\/a><\/td>.*'.
				'<td>(?P<date>.*)<\/td>.*'.
				'<td><nobr><a href="http:\/\/torrents.thepiratebay.org\/(?P<link>.*)".*<\/nobr><\/td>.*'.
				'<td align="right">(?P<size>.*)<\/td>.*'.
				'<td align="right">(?P<seeds>.*)<\/td>.*'.
				'<td align="right">(?P<leech>.*)<\/td>/siU', $cli->results, $matches);

			if(($res!==false) && ($res>0) &&
				count($matches["desc"])==count($matches["name"]) &&
				count($matches["cat"])==count($matches["name"]) && 
				count($matches["name"])==count($matches["date"]) &&
				count($matches["date"])==count($matches["link"]) &&
				count($matches["size"])==count($matches["link"]) &&
				count($matches["size"])==count($matches["seeds"]) &&
				count($matches["seeds"])==count($matches["leech"]) )
			{
				for($i=0; $i<count($matches["link"]); $i++)
				{
					$link = "http://torrents.thepiratebay.org/".$matches["link"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/torrent/".$matches["desc"][$i];
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize($matches["size"][$i]);
						$item["seeds"] = intval(self::removeTags($matches["seeds"][$i]));
						$item["peers"] = intval(self::removeTags($matches["leech"][$i]));

						$tm = self::removeTags($matches["date"][$i]);
						if(strpos($tm,":")!==false)
						{
							$tm = strptime($tm,"%m-%d %H:%M");
							$tm["tm_year"] = date("Y")-1900;
						}
						else
							$tm = strptime($tm,"%m-%d %Y");
						$item["time"] = mktime(	$tm["tm_hour"], $tm["tm_min"], $tm["tm_sec"], $tm["tm_mon"]+1, $tm["tm_mday"], $tm["tm_year"]+1900 );
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