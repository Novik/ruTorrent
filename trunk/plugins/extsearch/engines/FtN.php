<?php

class FtNEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>15, "auth"=>1 );
	public $categories = array( 'all'=>'', "Apps" => "&c2=1&c3=1", "DOX" => "&c4=1", "Games" => "&c8=1&c10=1&c11=1&c25=1&c12=1", 
		"Misc" => "&c13=1", "Movies" => "&c14=1&c24=1&c18=1", "Music" => "&c19=1", "Mvids" => "&c20=1", "TV" => "&c21=1&c22=1", "XXX" => "&c4=23" );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://feedthe.net';
		if($useGlobalCats)
			$categories = array( 'all'=>'', 'movies'=>"&c14=1&c24=1&c18=1", 'tv'=>"&c21=1&c22=1", 'music'=>"&c19=1", 'games'=>"&c8=1&c10=1&c11=1&c25=1&c12=1", 'software'=>"&c2=1&c3=1" );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		for($pg = 0; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/browse.php?search='.$what.'&sort=seeders&d=DESC&incldead=0&titleonly=1&page='.$pg.$cat );
			if( ($cli==false) || (strpos($cli->results, "<h3>Nothing found!</h3>")!==false) ||
				(strpos($cli->results, '<form method="post" action="takelogin.php">')!==false))
				break;
			$res = preg_match_all('/<tr>.*<td .*><a href="browse.php\?.*"><img border="0" src="\/pic\/.*" alt="(?P<cat>.*)".*\/><\/a><\/td>'.
				'.*<a href="details.php\?id=(?P<id>\d+)&amp;hit=1">(?P<name>.*)<\/a>.*<td .*>.*<\/td>.*<td .*>.*<\/td>'.
				'.*<td .*>(?P<date>.*)<\/td>.*<td .*>.*<\/td>.*<td .*>(?P<size>.*)<\/td>'.
				'.*<td .*>.*<\/td>.*<td .*>(?P<seeds>.*)<\/td>.*<td .*>(?P<leech>.*)<\/td>/siU', $cli->results, $matches);
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
					$name = self::removeTags($matches["name"][$i]);
					$link = $url.'/download.php/'.$matches["id"][$i].'/'.str_replace(" ","_",trim($name)).'.torrent';
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/details.php?id=".$matches["id"][$i]."&hit=1";
						$item["name"] = $name;
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
