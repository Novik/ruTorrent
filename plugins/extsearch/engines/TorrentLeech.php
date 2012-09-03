<?php

class TorrentLeechEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>100, "auth"=>1 );

	public $categories = array( 'all'=>'', 
		'Movies'=>'/categories/1,8,9,10,11,12,13,14,15,29',
		'TV'=>'/categories/2,26,27',
		'Games'=>'/categories/3,17,18,19,20,21,22,28,30',
		'Music'=>'/categories/4,16,31',
		'Books'=>'/categories/5',
		'Applications'=>'/categories/6,23,24,25',
		'Anime'=>'/categories/7',
		);

	protected static function getInnerCategory($cat)
	{
		$categories = array(
			'1'=>'Movies','8'=>'Movies','9'=>'Movies','10'=>'Movies','11'=>'Movies','12'=>'Movies','13'=>'Movies','14'=>'Movies','15'=>'Movies','29'=>'Movies',
			'2'=>'TV','26'=>'TV','27'=>'TV',
			'3'=>'Games','17'=>'Games','18'=>'Games','19'=>'Games','20'=>'Games','21'=>'Games','22'=>'Games','28'=>'Games','30'=>'Games',
			'4'=>'Music','16'=>'Music','31'=>'Music',
			'5'=>'Books',
			'6'=>'Applications','23'=>'Applications','24'=>'Applications','25'=>'Applications',
			'7'=>'Anime' );
		return(array_key_exists($cat,$categories) ? $categories[$cat] : '');
	}

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://www.torrentleech.org';
		if($useGlobalCats)
			$categories = array( 'all'=>'', 'movies'=>'/categories/1,8,9,10,11,12,13,14,15,29', 
				'tv'=>'/categories/2,26,27', 'music'=>'/categories/4,16,31', 'games'=>'/categories/3,17,18,19,20,21,22,28,30', 
				'anime'=>'/categories/7', 'software'=>'/categories/6,23,24,25', 'books'=>'/categories/5' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		for($pg = 1; $pg<11; $pg++)
		{
			$cli = $this->fetch( Snoopy::linkencode($url.'/torrents/browse/index/query/'.$what.'/orderby/seeders/order/desc/page/'.$pg).$cat, false );
                        if( ($cli==false) || 
				(strpos($cli->results, "There are no results found, based on your search parameters")!==false) ||
				(strpos($cli->results, ">Password")!==false))
				break;

			$res = preg_match_all('`<td class="category"><a href="/torrents/browse/index/categories/(?P<cat>\d*)">.*'.
	                        '<td class="name"><span class="title"><a href="/torrent/(?P<id>\d*)">(?P<name>.*)</a></span><br>.*'.
				'Added in <b>[^<]*</b> on (?P<date>[^<]*)</td>.*'.
				'<td class="quickdownload">.*</td>.*'.
				'<td>.*</td>.*'.
				'<td>(?P<size>[^<]*)</td>.*'.
				'<td>.*</td>.*'.
				'<td>(?P<seeds>.*)</td>.*'.
				'<td>(?P<leech>.*)</td>'.
				'`siU', $cli->results, $matches);

			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $url."/download/".$matches["id"][$i].'/dummy';
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::getInnerCategory($matches["cat"][$i]);
						$item["desc"] = $url."/torrent/".$matches["id"][$i];
						$item["name"] = self::removeTags($matches["name"][$i]);
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
			else
				break;
		}
	}
}
