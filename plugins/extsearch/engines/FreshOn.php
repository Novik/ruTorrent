<?php

class FreshOnEngine extends commonEngine
{
	public function action($what,$cat,&$ret,$limit)
	{
		$added = 0;
		$url = 'http://freshon.tv';
		$categories = array( 'all'=>'', 'anime'=>'235' );
		if(!array_key_exists($cat,$categories))
			$cat = 'all';

		for($pg = 0; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/browse.php?search='.$what.'&sort=seeders&d=DESC&incldead=0&page='.$pg.'&cat='.$categories[$cat] );
			if( ($cli==false) || (strpos($cli->results, "<strong>Nothing found</strong>")!==false) ||
				(strpos($cli->results, "<label>Password</label>")!==false))
				break;
			$res = preg_match_all('/<tr class="torrent_\d"><td class="table_categ_icon"><a.*onmouseover="return overlib\(\'(?P<cat>.*)\'\);".*<\/a><\/td><td class="table_name">.*'.
				'<a href="\/details.php\?id=(?P<id>\d+)" class="torrent_name_link".*title="(?P<name>.*)">.*'.
				'<td class="table_links"><a href="\/download.php\/(?P<tname>.*)".*'.
				'<td class="table_added">(?P<date>.*)<\/td><td class="table_size">(?P<size>.*)<\/td>.*'.
				'<td class="table_seeders">(?P<seeds>.*)<\/td><td class="table_leechers">(?P<leech>.*)<\/td>/siU', $cli->results, $matches);
			if(($res!==false) && ($res>0) &&
				count($matches["cat"])==count($matches["name"]) && 
				count($matches["name"])==count($matches["id"]) &&
				count($matches["name"])==count($matches["tname"]) &&
				count($matches["name"])==count($matches["date"]) &&
				count($matches["name"])==count($matches["size"]) &&
				count($matches["name"])==count($matches["seeds"]) &&
				count($matches["name"])==count($matches["leech"]) )
			{
				for($i=0; $i<count($matches["id"]); $i++)
				{
					$link = $url."/download.php/".$matches["tname"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/details.php?id=".$matches["id"][$i];
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize(trim(str_replace("<br/>"," ",$matches["size"][$i])));
						$item["time"] = strtotime(self::removeTags(trim(str_replace("<br>"," ",$matches["date"][$i]))));
						$item["seeds"] = intval(trim(self::removeTags($matches["seeds"][$i])));
						$item["peers"] = intval(trim(self::removeTags($matches["leech"][$i])));
						$ret[$link] = $item;
						$added++;
						if($added>=$limit)
							return;
					}
				}
			}
			else
				break;

			if(strpos($cli->results, '<span class="selected">»</span></div>')!==false)
				break;
		}
	}
}

?>