<?php

class ScCEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>25, "cookies"=>"www.sceneaccess.org|pass=XXX;uid=XXX" );

	public $categories = array( 'all'=>'', 
		'Movies/DVD-R'=>'&c8=1',
		'Movies/x264'=>'&c22=1',
		'Movies/XviD'=>'&c7=1',
		'TV/DVD-R'=>'&c17=1',
		'TV/DVDRip'=>'&c25=1',
		'TV/x264'=>'&c27=1',
		'TV/XviD'=>'&c11=1',
		'Games/PC'=>'&c3=1',
		'Games/PS3'=>'&c5=1',
		'Games/PSP'=>'&c20=1',
		'Games/WII'=>'&c28=1',
		'Games/XBOX360'=>'&c23=1',
		'APPS'=>'&c1=1',
		'DOX'=>'&c14=1',
		'MISC'=>'&c21=1' );

	
	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://www.sceneaccess.org';
		if($useGlobalCats)
			$categories = array( 
				'all'=>'', 'movies'=>'&c8=1&c22=1&c7=1', 
				'tv'=>'&c17=1&c25=1&c27=1&c11=1', 
				'games'=>'&c3=1&c5=1&c20=1&c28=1', 
				'software'=>'&c1=1' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		for($pg = 0; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/browse?method=2&search='.$what.'&sort=6&type=descC&page='.$pg.$cat );
			if( ($cli==false) || (strpos($cli->results, "<h2>Nothing found!</h2>")!==false)
				|| (strpos($cli->results, 'value="password"')!==false))
				break;
			$res = preg_match_all('`<td class="ttr_type"><a href=.*><img src="/pic/.* alt="(?P<cat>[^"]*)".*</td>.*'.
				'<td class="ttr_name"><a href="details\?id=(?P<id>\d+)"\s+title="(?P<name>[^"]*)".*</td>.*'.
				'<td class="td_dl"><a href="download/(?P<link>[^"]*)">.*</td>.*'.
				'<td class="ttr_size">(?P<size>.*)<.*</td>.*'.
				'<td class="ttr_added">(?P<date>.*)</td>.*'.
				'<td class="ttr_seeders">(?P<seeds>.*)</td>.*'.
				'<td class="ttr_leechers">(?P<leech>.*)</td>'.
				'`siU', $cli->results, $matches);
			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $url."/download/".$matches["link"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/details.php?id=".$matches["id"][$i];
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize(str_replace("<br />"," ",$matches["size"][$i]));
						$item["time"] = strtotime(self::removeTags(str_replace("<br />"," ",$matches["date"][$i])));
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
