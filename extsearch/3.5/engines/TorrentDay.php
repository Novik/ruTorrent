<?php

class TorrentDayEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>35, "cookies"=>"www.torrentday.com|pass=XXX;uid=XXX;" );

	public $categories = array( 'all'=>'', 'Movies'=>'&c1=1', 'TV'=>'&c2=1', 'Movies/DVD-R'=>'&c3=1',
		'PC Games'=>'&c4=1', 'PS2'=>'&c5=1','XXX'=>'&c6=1',
		'TV-X264'=>'&c7=1', 'PSP'=>'&c8=1', 'XBOX360'=>'&c9=1', 'WII'=>'&c10=1', 'X264'=>'&c11=1',
		'Movie Packs'=>'&c13=1', 'TV Packs'=>'&c14=1', 'XXX Packs'=>'&c15=1', 'Music Videos'=>'&c16=1' );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://www.torrentday.com';
		if($useGlobalCats)
			$categories = array( 'all'=>'', 'music'=>'&c16=1' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		for($pg = 0; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/browse.php?search='.$what.'&sort=7&type=desc&page='.$pg.$cat );
			if( ($cli==false) || (strpos($cli->results, "<h2>Nothing found!</h2>")!==false) ||
				(strpos($cli->results, "<h1>Not logged in!</h1>")!==false))
				break;
			$res = preg_match_all('/href="browse.php\?cat=\d+"><img border="0".*'.
				'<a href="details.php\?id=(?P<id>\d+)">(?P<name>.*)<\/a><br>Uploaded: (?P<date>.*)<\/td>.*'.
				'<a class="index" href="download\.php\/\d+\/(?P<tname>.*)">.*<\/td><td class=.*<\/td>.*'.
				'<td class=.*>(?P<size>.*)<\/td>.*'.
				'<td class=.*>(?P<seeds>.*)<\/td>.*'.
				'<td class=.*>(?P<leech>.*)<\/td>/siU', $cli->results, $matches);
			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $url."/download.php/".$matches["id"][$i]."/".$matches["tname"][$i];
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
