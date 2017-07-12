<?php

class ScCEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>50, "cookies"=>"sceneaccess.eu|pass=XXX;uid=XXX;" );

	public $categories = array( 'all'=>'',
		'Movies/DVD-R'=>'&c8=8',
		'Movies/x264'=>'&c22=22',
		'Movies/XviD'=>'&c7=7',
		'TV/HD-x264'=>'&c27=27',
		'TV/SD-x264'=>'&c17=17',
		'TV/XviD'=>'&c11=11',
		'Games/PC'=>'&c3=3',
		'Games/PS3'=>'&c5=5',
		'Games/PSP'=>'&c20=20',
		'Games/WII'=>'&c28=28',
		'Games/XBOX360'=>'&c23=23',
		'APPS/ISO'=>'&c1=1',
		'DOX'=>'&c14=14',
		'MISC'=>'&c21=21' );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://sceneaccess.eu';
		if($useGlobalCats)
			$categories = array(
				'all'=>'', 'movies'=>'&c8=8&c22=22&c7=7',
				'tv'=>'&c27=27&c17=17&c11=11',
				'games'=>'&c3=3&c5=5&c20=20&c28=28',
				'software'=>'&c1=1' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		for($pg = 0; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/browse?method=2&search='.$what.'&sort=6&type=desc&page='.$pg.$cat );
			if( ($cli==false) || (strpos($cli->results, "<h2>Nothing found!</h2>")!==false)
				|| (strpos($cli->results, 'value="password"')!==false))
				break;
			$res = preg_match_all('/type">.*<img src="\/pic\/.*" alt="(?P<cat>.*(?=")).*'.
				'name"><a href="details\?id=(?P<id>\d+)" title="(?P<name>.*)">.*'.
				'dl"><a href="download\/(?P<link>.*)">.*'.
				'size">(?P<size>.*)<.*'.
				'added">(?P<date>.*)<\/td>.*'.
				'seeders">(?P<seeds>.*)<\/td>.*'.
				'leechers">(?P<leech>.*)<\/tr>'.
				'/siU', $cli->results, $matches);
			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $url."/download/".$matches["link"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/details?id=".$matches["id"][$i];
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize(str_replace("<br/>"," ",$matches["size"][$i]));
						$item["time"] = strtotime(self::removeTags(str_replace("<br/>"," ",$matches["date"][$i])));
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
