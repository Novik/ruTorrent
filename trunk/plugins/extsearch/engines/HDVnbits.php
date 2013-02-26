<?php

class HDVnbitsEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>50, "cookies"=>"hdvnbits.org|c_secure_pass=XXX;c_secure_uid=XXX;c_secure_login=XXX;" );
	public $categories = array(
		'all'=>'0','Game'=>'1','Movie'=>'2','TV'=>'3','Software'=>'4','Music'=>'5','Misc'=>'6',
		);

	public $reverseCats = array
	(
		7=>"PC", 133=>"Handheld", 132=>"Console", 23=>"mHD", 24=>"SD",
		124=>"720p", 125=>"1080p", 127=>"Blu-ray", 76=>"Windows", 77=>"MAC",
		78=>"Linux", 79=>"Handheld", 92=>"Music Video", 126=>"Lossless", 
		130=>"Lossy", 131=>"Surround", 112=>"Ebook", 113=>"Training Video",
		115=>"Image", 116=>"Manga", 117=>"Audio book", 128=>"HD", 129=>"SD",
	);

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://hdvnbits.org';

		if($useGlobalCats)
			$categories = array( 'all'=>'0', 'movies'=>'2', 'tv'=>'3', 'music'=>'5', 'games'=>'1', 'software'=>'4' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		for($pg = 0; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/torrents.php?search='.$what.'&incldead=1&sort=7&type=desc&page='.$pg.'&sltCategory='.$cat );
			if( ($cli==false) || (strpos($cli->results, "Nothing found! Try again with a refined search string")!==false))
				break;
			$result = $cli->results;
			$first = strpos($result, '<form method="get" name="searchbox" action="?">');
			if($first!==false)
				$result = substr($result,$first);

			$res = preg_match_all('`<a href="/torrents.php\?sltSubCategory=(?P<cat>\d*)"><img src=.*</a></td>.*'.
				'<table class="torrentname" width="100%"><tr><td class="embedded"><a class="" title="(?P<name>[^"]*)"  href="(?P<desc>[^"]*)".*'.
				'<a href="/download.php\?id=(?P<id>\d*)".*'.
				'</tr></table></td>.*</td><td class="rowfollow nowrap">(?P<date>.*)</td><td class="rowfollow">(?P<size>.*)</td><td class="rowfollow" align="center">(?P<seeds>.*)</td>.*'.
				'<td class="rowfollow">(?P<leech>.*)</td>'.
				'`siU', $result, $matches);
			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $url."/download.php?id=".$matches["id"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = $this->reverseCats[self::removeTags($matches["cat"][$i])];
						$item["desc"] = $url.self::removeTags($matches["desc"][$i]);
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize(str_replace("<br />"," ",$matches["size"][$i]));
						$item["time"] = strtotime(self::removeTags(trim(str_replace("<br />"," ",$matches["date"][$i]))));
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
