<?php

class RevolutionTTEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>50, "auth"=>1 );
	public $categories = array(
		'All'=>'&cat=0',
		'Anime'=>'&cat=23',
		'Appz/Misc'=>'&cat=22',
		'Appz/PC-ISO'=>'&cat=1',
		'E-Book'=>'&cat=36',
		'Games/PC-ISO'=>'&cat=4',
		'Games/PC-Rips'=>'&cat=21',
		'Games/PS3'=>'&cat=16',
		'Games/Wii'=>'&cat=40',
		'Games/XBOX360'=>'&cat=39',
		'Handheld/NDS'=>'&cat=35',
		'Handheld/PSP'=>'&cat=34',
		'Mac'=>'&cat=2',
		'Movies/BluRay'=>'&cat=10',
		'Movies/DVDR'=>'&cat=20',
		'Movies/HDx264'=>'&cat=12',
		'Movies/Packs'=>'&cat=44',
		'Movies/SDx264'=>'&cat=11',
		'Movies/XviD'=>'&cat=19',
		'Music'=>'&cat=6',
		'Music/FLAC'=>'&cat=8',
		'Music/Packs'=>'&cat=46',
		'MusicVideos'=>'&cat=29',
		'TV/DVDR'=>'&cat=43',
		'TV/HDx264'=>'&cat=42',
		'TV/Packs'=>'&cat=45',
		'TV/SDx264'=>'&cat=41',
		'TV/XViD'=>'&cat=7',
		'XXX'=>'&cat=9',
		'XXX/0DAY'=>'&cat=49',
		'XXX/DVDR'=>'&cat=47',
		'XXX/HD'=>'&cat=48'
		);

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://revolutiontt.me';
		if($useGlobalCats)
			$categories = array(
				'all'=>'&cat=0',
				'anime'=>'&cat=23',
				'books'=>'&cat=36'
				);
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		for($pg = 0; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/browse.php?search='.$what.'&titleonly=1&sort=7&type=desc&page='.$pg.$cat );
			if( ($cli==false) || (strpos($cli->results, "<h2>Nothing Found!</h2>")!==false)
				|| (strpos($cli->results, '>Login</title>')!==false))
				break;
			$res = preg_match_all('`<img border="0" src="/pic/caticons/.*" alt="(?P<cat>.*)" />.*'.
				'<td .*><a .*>(?P<name>.*)</a>.*</td>.*'.
				'<td .*>.*</td>.*'.
				'<td .*><a href="download.php/(?P<id>.*)/(?P<tname>.*)">.*</td>.*'.
				'<td .*>.*</td>.*'.
				'<td .*>(?P<date>.*)</td>.*'.
				'<td .*>(?P<size>.*)</td>.*'.
				'<td .*>.*</td>.*'.
				'<td .*>(?P<seeds>.*)</td>.*'.
				'<td .*>(?P<leech>.*)</td>'.
				'`siU', $cli->results, $matches);
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
					$link = $url."/download.php/".$matches["id"][$i]."/".$matches["tname"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/details.php?id=".$matches["id"][$i].'&hit=1';
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize(str_replace("<br>"," ",$matches["size"][$i]));
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
