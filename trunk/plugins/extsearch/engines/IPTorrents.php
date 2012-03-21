<?php

class IPTorrentsEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>35, "cookies"=>"on.iptorrents.com|pass=XXX;uid=XXX" );
	public $categories = array( 'all'=>'l72;l73;l74;l75;l76;',
		'Movies'=>'l72;', 'TV'=>'l73;', 'Games'=>'l74;', 'Music'=>'l75;', 'Books'=>'l35;', 'Anime'=>'l60;', 
		'Appz/misc'=>'l1;', 'MAC'=>'l69;', 'Mobile'=>'l58;', 'Pics/Wallpapers'=>'l36;', 'Sports'=>'l55;', 'XXX'=>'l8;' );


	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://on.iptorrents.com';
		if($useGlobalCats)
			$categories = array( 'all'=>'l72;l73;l74;l75;l76;', 
				'movies'=>'l72;', 'tv'=>'l73;', 'music'=>'l75;', 'games'=>'l74;', 
				'anime'=>'l60;', 'software'=>'l1;', 'pictures'=>'l36;', 'books'=>'l64;l35;' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		for($pg = 1; $pg<11; $pg++)
		{
			$cli = $this->fetch( $url.'/torrents/?'.$cat.'o=seeders;q=@title%20'.$what.';p='.$pg );
			if( ($cli==false) || (strpos($cli->results, ">Nothing found!<")!==false) ||
				(strpos($cli->results, ">Password:<")!==false))
				break;
			$res = preg_match_all('`<img class=".*" border="0" width="50" src=.* alt="(?P<cat>.*)" /></a>.*'.
				'<a href="/details\.php\?id=(?P<id>\d+)">(?P<name>.*)</a>.*<td .*>.*</td>.*'.
				'<td .*>.*href="/download\.php/\d+\/(?P<tname>.*)".*</a></td>.*'.
				'<td .*>.*</td>.*<td .*>(?P<date>.*)</td>.*<td .*>(?P<size>.*)</td>.*'.
				'<td .*>.*</td>.*<td .*>(?P<seeds>.*)</td>.*<td .*>(?P<leech>.*)</td>'.
				'`siU', $cli->results, $matches);
			if($res)
			{
				for($i=0; $i<count($matches["id"]); $i++)
				{
					$link = $url."/download.php/".$matches["id"][$i]."/".$matches["tname"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/details.php?id=".$matches["id"][$i];
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

?>