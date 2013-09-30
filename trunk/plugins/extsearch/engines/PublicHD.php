<?php

class PublicHDEngine extends commonEngine
{
	public $defaults = array( "public"=>true, "page_size"=>30 );
	public $categories = array(
		'all'=>'',
		'BluRay 720p'=>"&category=2",
		'BluRay 1080p'=>"&category=5",
		'BluRay Remux'=>"&category=8",
		'BluRay'=>"&category=9",
		'BluRay 3D'=>"&category=20",
		'XviD'=>"&category=15",
		'BRRip'=>"&category=16",
		'Movies Pack'=>"&category=3",
		'HD Music Video'=>"&category=22",
		'HDTV'=>"&category=7",
		'SDTV'=>"&category=24",
		'TV WEB-DL'=>"&category=14",
		'TV Packs'=>"&category=23",
		'Games PC'=>"&category=25",
		'Games PS3'=>"&category=26",
		'Games XBOX'=>"&category=27",
	);

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://publichd.se';

		if($useGlobalCats)
			$categories = array( 'all'=>'', 'movies'=>'&category=2;5;8;9;20;15;16;3;7;24', 'tv'=>'&category=14;23', 'music'=>'&category=22', 'games'=>'&category=25;26;27' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];

		for($pg = 1; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/index.php?page=torrents&search='.$what.'&active=1&order=5&by=2'.$cat.'&pages='.$pg );
			
			if( ($cli==false) )		break;

			$res = preg_match_all('`<td align="center".*<img src="[^"]*images/categories/[^"]*" border="0"  alt="(?P<cat>[^"]*)".*'.
				'<a href="index.php\?page=torrent-details&amp;id=(?P<id>[^"]*)" title="View details: [^"]*">(?P<name>.*)</a>.*'.
				'href="download\.php(?P<link>[^"]*)">.*'.
				'<td align="center" class="header" width="85">(?P<date>.*)</td>.*'.
				'<td align="center" class="header" width="30">(?P<seeds>.*)</td>.*'.
				'<td align="center" class="header" width="30">(?P<leech>.*)</td>.*'.
				'<td align="center" class="header" width="55"><b>(?P<size>.*)</b></td>'.
				'`siU', $cli->results, $matches);

			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $url."/download.php".$matches["link"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/index.php?page=torrent-details&id=".$matches["id"][$i];
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize(trim(str_replace("<br>"," ",$matches["size"][$i])));
						$item["time"] = strtotime(self::removeTags(trim(str_replace("<br />"," ",str_replace("/",".",$matches["date"][$i])))));
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
		}
	}
}
