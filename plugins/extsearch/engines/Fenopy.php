<?php

class FenopyEngine extends commonEngine
{
	static protected function getInnerCategory($cat)
	{
		$categories = array( '3'=>'Movies', '1'=>'Music', '78'=>'TV shows', '4'=>'Games', '6'=>'Applications', '2'=>'Videos', '5'=>'Anime', '7'=>'Books', '72'=>'Other' );
		return(array_key_exists($cat,$categories) ? $categories[$cat] : '');
	}
	public function action($what,$cat,&$ret,$limit)
	{
		$added = 0;
		$url = 'http://fenopy.com';
		$categories = array( 'all'=>'0', 'movies'=>'3', 'tv'=>'78', 'music'=>'1', 'games'=>'4', 'anime'=>'5', 'software'=>'6', 'pictures'=>'72', 'books'=>'7' );
		if(!array_key_exists($cat,$categories))
			$cat = 'all';
		$maxPage = 10;
		for($pg = 0; $pg<$maxPage; $pg++)
		{
			$cli = $this->fetch( $url.'/?keyword='.$what.'&inside=0&cat='.$categories[$cat].'&order=2&start='.$pg*50 );
			if( ($cli==false) || (strpos($cli->results, "<h2>No match found</h2>")!==false))
				break;
			$pos = strpos($cli->results, "<span class=\"page_no\">Page 1 of ");
			if($pos!=false)
				$maxPage = intval(substr($cli->results,$pos+32));
			$res = preg_match_all('/<td.*><a href="\/torrent\/(?P<desc>.*)" title="(?P<name>.*)".*class="cat_(?P<cat>\d{1,2})".*<\/a><\/td>.*'.
				'<td class="se">(?P<seeds>.*)<\/td>.*<td class="le">(?P<leech>.*)<\/td>.*<td class="si">(?P<size>.*)<\/td>.*'.
				'<td.*>.*<a href="\/torrent\/(?P<link>.*)\/download.torrent"/siU', $cli->results, $matches);
			if(($res!==false) && ($res>0) &&
				count($matches["desc"])==count($matches["name"]) &&
				count($matches["cat"])==count($matches["name"]) && 
				count($matches["name"])==count($matches["size"]) &&
				count($matches["size"])==count($matches["seeds"]) &&
				count($matches["size"])==count($matches["link"]) &&
				count($matches["seeds"])==count($matches["leech"]) )
			{
				for($i=0; $i<count($matches["link"]); $i++)
				{
					$link = $url."/torrent/".$matches["link"][$i]."/download.torrent";
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::getInnerCategory($matches["cat"][$i]);
						$item["desc"] = $url."/torrent/".$matches["desc"][$i];
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
			else
				break;
		}
	}
}

?>