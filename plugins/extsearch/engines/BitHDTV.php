<?php

/*
 *@author AceP1983
*/


class BitHDTVEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>50, "auth"=>1 );
	public $categories = array( 'all'=>'&cat=0', 'Anime'=>'&cat=1', 'Blu-ray'=>'&cat=2', 'Demo'=>'&cat=3', 'Doc'=>'&cat=4', 'HQ-Audio'=>'&cat=6', 'Movies'=>'&cat=7', 'Music Videos'=>'&cat=8', 'Other'=>'&cat=9', 'HD-DVD'=>'&cat=5', 'TV'=>'&cat=10', 'TV/Season'=>'&cat=12', 'XXX'=>'&cat=11' );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://www.bit-hdtv.com';
		if($useGlobalCats)
			$categories = array( 'all'=>'&cat=0' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		for($pg = 0; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/torrents.php?search='.$what.'&sort=7&type=desc&page='.$pg.$cat );
			if( ($cli==false) || (strpos($cli->results, "<h2>No match!</h2>")!==false) 
				|| (strpos($cli->results, '>Password:<')!==false))
				break;
			$res = preg_match_all('/<img border="0" src=.* alt="(?P<cat>.*)" \/><\/a>'.
				'.*<td.*>.*href="\/details.php\?id=(?P<id>\d+)".*>(?P<name>.*)<\/a>'.
				'.*<a href="\/download.php\?\/\d+\/(?P<tname>.*)"><\/a>.*'.
				'<td .*>.*<\/td>.*'.
				'<td .*>.*<\/td>.*'.
				'<td .*>(?P<date>.*)<\/td>'.
				'.*<td .*>(?P<size>.*)<\/td>'.
				'.*<td .*>.*<\/td>.*'.
				'.*<td .*>(?P<seeds>.*)<\/td>.*<td .*>(?P<leech>.*)<\/td>/siU', $cli->results, $matches);

			if(($res!==false) && ($res>0) &&
				count($matches["id"])==count($matches["cat"]) &&
				count($matches["cat"])==count($matches["name"]) && 
				count($matches["name"])==count($matches["size"]) &&
				count($matches["size"])==count($matches["seeds"]) &&
				count($matches["seeds"])==count($matches["date"]) &&
				count($matches["seeds"])==count($matches["tname"]) &&
				count($matches["date"])==count($matches["leech"]) )
			{
				for($i=0; $i<count($matches["id"]); $i++)
				{
					$link = $url."/download.php?/".$matches["id"][$i]."/".$matches["tname"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/details.php?id=".$matches["id"][$i];
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize(str_replace("<br>"," ",$matches["size"][$i]));
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
			else
				break;
		}
	}
}
