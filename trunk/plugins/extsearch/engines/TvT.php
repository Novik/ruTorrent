<?php

class TvTEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>15, "cookies"=>"freshon.tv|pass=XXX;uid=XXX" );
	public $categories = array( 'all'=>'', '...2010 World Cup Africa...'=>"457", '..Anime..'=>"235",
		'..Cartoons..'=>"17", '..Comedy..'=>"262", '..Documentaries..'=>"15", '..Miniseries..'=>"231",
		'..NBA..'=>"450", '..Other..'=>"16", '..Poker..'=>"138", '..Reality-TV..'=>"13", '..Sports..'=>"156" );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://freshon.tv';

		if($useGlobalCats)
			$categories = array( 'all'=>'', 'anime'=>'235' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];

		for($pg = 0; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/browse.php?search='.$what.'&sort=seeders&d=DESC&incldead=0&page='.$pg.'&cat='.$cat );
			if( ($cli==false) || (strpos($cli->results, "<strong>Nothing found</strong>")!==false) ||
				(strpos($cli->results, "<label>Password</label>")!==false))
				break;
			$res = preg_match_all('/<tr class="torrent_\d"><td class="table_categ_icon"><a.*onmouseover="return overlib\(\'(?P<cat>.*)\'\);".*<\/a><\/td><td class="table_name">.*'.
				'<a href="\/details.php\?id=(?P<id>\d+)" class="torrent_name_link".*title="(?P<name>.*)">.*'.
				'<td class="table_added">(?P<date>.*)<\/td><td class="table_size">(?P<size>.*)<\/td>.*'.
				'<td class="table_seeders">(?P<seeds>.*)<\/td><td class="table_leechers">(?P<leech>.*)<\/td>/siU', $cli->results, $matches);
			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $url."/download.php?id=".$matches["id"][$i]."&type=torrent";
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
