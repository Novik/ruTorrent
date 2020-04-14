<?php

/*
 *@author AceP1983, Novik
*/

class BitHDTVEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>50, "cookies"=>"www.bit-hdtv.com|h_sl=XXX;h_sp=XXX;h_su=XXX" );
	public $categories = array
	(
		'All'=>'&cat=0',
		'Movies'=>'&cat=7',
		'TV'=>'&cat=10',
		'TV/Season'=>'&cat=12',
		'XXX'=>'&cat=11',
		'Music Videos'=>'&cat=8',
		'Audio Tracks'=>'&cat=6',
		'Other'=>'&cat=9',
	);

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://www.bit-hdtv.com';
		if($useGlobalCats)
		{
			$categories = array
			(
				'all'=>'&cat=0',
				'movies'=>'&cat=7',
				'tv'=>'&cat=10',
				'music'=>'&cat=6',
			);
		}
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		for($pg = 0; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/torrents.php?search='.$what.'&sort=7&type=desc&options=0&seeded=1&page='.$pg.$cat );
			if( ($cli==false) || (strpos($cli->results, "<h2>No match!</h2>")!==false)
				|| (strpos($cli->results, '>Password:<')!==false))
				break;
			$res = preg_match_all('`<a href="torrents\.php\?cat=.*" alt=\'(?P<cat>.*)\' >.*</a></td>.*'.
				'<td .*><a title="(?P<name>.*)" href=\'.*id=(?P<id>\d+)\'">.*</td>.*'.
				'<td .*>.*</td>.*'.
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
				count($matches["date"])==count($matches["leech"]) )
			{
				for($i=0; $i<count($matches["id"]); $i++)
				{
					$link = $url."/download.php?id=".$matches["id"][$i];
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
