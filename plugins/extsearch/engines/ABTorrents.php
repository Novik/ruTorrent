<?php

class ABTorrentsEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>15, "auth"=>1 );
	public $categories = array(
		"all"=>"&c0=1", "Adventure"=>"&c10=1", "Biographies & Memoirs"=>"&c20=1", "Business"=>"&c30=1", "Childrens"=>"&c40=1",
		"Comedy"=>"&c50=1", "Comics"=>"&c60=1", "Computers"=>"&c70=1", "Erotica"=>"&c80=1", "Fantasy-General"=>"&c90=1", "Fantasy-Youth"=>"&c100=1",
		"Files"=>"&c110=1", "Foreign Language"=>"&c120=1", "General Fiction"=>"&c130=1", "Historical Fiction"=>"&c140=1", "History"=>"&c150=1",
		"Horror"=>"&c160=1", "Literature"=>"&c170=1", "Mystery"=>"&c180=1", "Non-Fiction"=>"&c190=1", "Radio Drama"=>"&c200=1", "Romance"=>"&c210=1",
		"Science"=>"&c220=1", "Science Fiction"=>"&c230=1", "Sci-Fi Apocalypse"=>"&c235=1", "Self Improvement"=>"&c240=1", "Thriller and Suspense"=>"&c245=1",
		"Suspense"=>"&c250=1", "Talk Radio"=>"&c260=1", "Urban Fantasy"=>"&c270=1", "Western"=>"&c280=1"
		);

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://abtorrents.me';
		if($useGlobalCats)
			$categories = array( 'all'=>'&c0=1' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		$what = rawurlencode(rawurldecode($what));
		for($pg = 0; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/browse.php?search='.$what.'&page='.$pg.'&searchin=title&incldead=0&sort=7&type=desc'.$cat );

			if( ($cli==false) || (strpos($cli->results, ">Nothing found!<")!==false)
				|| (strpos($cli->results, '>Password:<')!==false) )
				break;

			if( (strpos($cli->results, 'action="pm_system.php"')!==false) )
			{
				$item = $this->getNewEntry();
				$item["desc"] = $url."/pm_system.php?action=view_mailbox";
				$item["name"] = "ABTorrents > Double-click here then read all unread messages to grant torrent access";
				$ret[$link] = $item;
				break;
			}

			$res = preg_match_all('`<a href=\'browse\.php\?cat=\d+\'><img border=\'0\' src=\'.*\' alt=\'(?P<cat>.*)\' /></a>.*'.
				'<a href=\'(?P<desc>.*)\' onmouseover="Tip\(\'(?P<name>.*)<br /><b>Size:&nbsp;(?P<size>.*)</b>.*'.
				'(?:(?:&nbsp;){4}|(?P<vip>Vip Torrent)).*'.
				'<td align=\'center\'><a href="(?P<link>.*)">.*'.
				'<span style=\'white-space: nowrap;\'>(?P<date>.*)</span>.*'.
				'<font .*>(?P<seeds>\d+)</font>.*'.
				'<td .*>(?P<leech>\d+)</td>'.
				'`siU', $cli->results, $matches);
			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $url."/".self::removeTags($matches["link"][$i]);
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/".self::removeTags($matches["desc"][$i]);
						$name = str_replace("<br />"," ",$matches["name"][$i]);
						if($matches["vip"][$i])
							$name = $name." | VIP TORRENT";
						$item["name"] = self::removeTags($name);
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
