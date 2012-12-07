<?php

class ISOHuntEngine extends commonEngine
{
	public $defaults = array( "public"=>true, "page_size"=>20, "disabled"=>true );
	public $categories = array( 'all'=>'', 'Video / Movies'=>'1', 'TV'=>'3', 'Audio'=>'2',
		'Music Video'=>'10', 'Games'=>'4', 'Applications'=>'5', 'Pictures'=>'6', 'Anime'=>'7', 'Comics'=>'8', 
		'Books'=>'9', 'Misc'=>'0', 'Unclassified'=>'11' );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://isohunt.com';
		if($useGlobalCats)
			$categories = array( 'all'=>'', 'movies'=>'1', 'tv'=>'3', 'music'=>'2', 'games'=>'4', 'anime'=>'7', 'software'=>'5', 'pictures'=>'6', 'books'=>'9' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];

		for($pg = 1; $pg<11; $pg++)
		{
			$itemsOnPage = 0;
			$cli = $this->fetch( $url.'/torrents.php?ihq='.$what.'&iht='.$cat.'&ihp='.$pg.'&ihs1=2&iho1=d' );
			if( ($cli==false) || (strpos($cli->results, "Search returned 0 results.")!==false) )
				break;
			$result = $cli->results;
			$res = preg_match_all("'<tr class=\"hlRow\" (.*?)>(.*?)</tr>'si", $result, $items);
			if(($res!==false) && ($res>0))
			{
				for( $i=0; $i<count($items[2]); $i++)
				{
					$res = preg_match_all("'<td(| .*?)>(.*?)</td>'si", $items[2][$i], $tds);
		                        if(($res!==false) && ($res>0) && (count($tds[2])>5) &&
						((preg_match( "/href=[\"']\/download\/(?P<id>\d+)\/(.*?)[\"']>(?P<name>.*)<\/a>/siU", $items[2][$i], $matches )==1) ||
						(preg_match( "/href=[\"']\/torrent_details\/(?P<id>\d+)\/(.*?)\?tab=summary[\"']>(?P<name>.*)<\/a>/siU", $items[2][$i], $matches )==1)))
					{
						$item = $this->getNewEntry();
						$link = $url."/download/".$matches["id"];
						$itemsOnPage++;
						if(!array_key_exists($link,$ret))
						{
							$item["desc"] = $url."/torrent_details/".$matches["id"];
							$name = $matches["name"];
							$pos = strrpos($name,"<br>");
							if($pos!==false)
								$name = substr($name,$pos+4);
							$item["name"] = self::removeTags($name);
							$ctg = $tds[2][0];
							$pos = strpos($ctg,"<br>");
							if($pos!==false)
								$ctg = substr($ctg,0,$pos);
							$item["cat"] = self::removeTags($ctg);
							$tm = self::removeTags($tds[2][1]);
							$len = strlen($tm);
							$now = time();
							if($len)
							{
								switch($tm[$len-1])
								{
									case 'w':
										$tm = round($now-(floatval($tm)*7*24*3600));
										break;
									case 'd':
										$tm = round($now-(floatval($tm)*24*3600));
										break;
									default :
										$tm = round($now-(floatval($tm)*3600));
										break;
								}
								$item["time"] = intval($tm);
							}
							$item["size"] = self::formatSize($tds[2][3]);
							$item["seeds"] = intval(self::removeTags($tds[2][4]));
							$item["peers"] = intval(self::removeTags($tds[2][5]));
							$ret[$link] = $item;
							$added++;
							if($added>=$limit)
								return;
						}
					}
				}
			}
			if(!$itemsOnPage)
				return;
		}
	}
}
