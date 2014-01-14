<?php

class SceneHDEngine extends commonEngine
{
       	public $defaults = array( "public"=>false, "page_size"=>50, "cookies"=>"scenehd.org|pass=XXX;uid=XXX;" );
	public $categories = array( 'all'=>'', 'Movie/1080'=>'&cat=1', 'Movie/720'=>'&cat=4', 'Movie/BD5/9'=>'&cat=8', 'Movie/Complete'=>'&cat=22', 
		'TV/1080'=>'&cat=5', 'TV/720'=>'&cat=7', 'WMV-HD'=>'&cat=11', 'XXX'=>'&cat=10', 'MVID'=>'&cat=13', 'Subpacks'=>'&cat=16', 'Other'=>'&cat=9' ); 

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://scenehd.org';
		if($useGlobalCats)
			$categories = array( 'all'=>'', 'movies'=>'&cat=1,4,8,22', 'tv'=>'&cat=5,7' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];

		for($pg = 0; $pg<11; $pg++)
		{
			$cli = $this->fetch( Snoopy::linkencode($url.'/browse.php?search='.$what.'&sort=9&page='.$pg).'&cat='.$cat,false );
			if( ($cli==false) || (strpos($cli->results, "<h2>No torrents found!</h2>")!==false) ||
				(strpos($cli->results, "<td>Password</td>")!==false))
				break;
			$res = preg_match_all('`<img border="0" src="[^"]*" title="(?P<cat>[^"]*)"><\/a><\/td>.*'.
				'<a class="(?P<stat>[^"]*)" href="details\.php\?id=(?P<id>\d+)" title="(?P<name>[^"]*)">.*<\/td>.*'.
				'<nobr>(?P<size>[^<]*)<br>.*'.
				'<td.*>(?P<date>.*\d+)<\/td>.*'.	
				'<span.*>(?P<seeds>.*\d++)<\/span>.*'.
				'\/.\n.*<span.*>(?P<leech>\d++)'.
				'`siU', $cli->results, $matches);
			if(($res!==false) && ($res>0) &&
				count($matches["cat"])==count($matches["id"]) &&
				count($matches["id"])==count($matches["name"]) && 
				count($matches["name"])==count($matches["size"]) &&
				count($matches["size"])==count($matches["seeds"]) &&
				count($matches["size"])==count($matches["date"]) &&
				count($matches["seeds"])==count($matches["leech"]) )
			{
				for($i=0; $i<count($matches["id"]); $i++)
				{
					$link = $url."/download.php?id=".$matches["id"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = $matches["cat"][$i];
						$item["desc"] = $url."/details.php?id=".$matches["id"][$i];
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize($matches["size"][$i]);
						$item["time"] = strtotime(self::removeTags(str_replace("<br>"," ",$matches["date"][$i])));
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
