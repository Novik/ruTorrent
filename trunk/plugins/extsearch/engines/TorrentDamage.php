<?php

class TorrentDamageEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>25, "auth"=>1 );
	public $categories = array( 'all'=>'', "Anime"=>"&filter_cat[1]=1&filter_cat[2]=1&filter_cat[3]=1",
		"Appz"=>"&filter_cat[4]=1&filter_cat[5]=1&filter_cat[6]=1&filter_cat[7]=1",
		"Games"=>"&filter_cat[8]=1&filter_cat[9]=1&filter_cat[10]=1&filter_cat[11]=1&filter_cat[12]=1&filter_cat[13]=1&filter_cat[14]=1",
		"Movies"=>"&filter_cat[15]=1&filter_cat[16]=1&filter_cat[17]=1&filter_cat[18]=1",
		"Music"=>"&filter_cat[19]=1&filter_cat[20]=1&filter_cat[21]=1",
		"TV"=>"&filter_cat[22]=1&filter_cat[23]=1&filter_cat[24]=1",
		"XXX"=>"&filter_cat[25]=1&filter_cat[26]=1&filter_cat[27]=1" );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://www.torrent-damage.net';
		if($useGlobalCats)
			$categories = array( 'all'=>'0', 
				'movies'=>"&filter_cat[15]=1&filter_cat[16]=1&filter_cat[17]=1&filter_cat[18]=1", 
				'tv'=>"&filter_cat[22]=1&filter_cat[23]=1&filter_cat[24]=1", 
				'music'=>"&filter_cat[19]=1&filter_cat[20]=1&filter_cat[21]=1", 
				'games'=>"&filter_cat[8]=1&filter_cat[9]=1&filter_cat[10]=1&filter_cat[11]=1&filter_cat[12]=1&filter_cat[13]=1&filter_cat[14]=1", 
				'anime'=>"&filter_cat[1]=1&filter_cat[2]=1&filter_cat[3]=1", 
				'software'=>"&filter_cat[4]=1&filter_cat[5]=1&filter_cat[6]=1&filter_cat[7]=1" );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];

		for($pg = 1; $pg<11; $pg++)
		{
			$cli = $this->fetch( $url.'/torrents.php?torrentname='.$what.$cat.'&order_by=s6&order_way=desc&disablegrouping=1&page='.$pg );			
			if( ($cli==false) || (strpos($cli->results, "<h3>No Results Found!</h3>")!==false) ||
				(strpos($cli->results, ">Password<")!==false))
				break;
			$res = preg_match_all('`<img src="http://www\.torrent-damage\.net/static/common/caticons/[^"]*"\s*alt="(?P<cat>[^"]*)".*'.
				'<a href="torrents\.php\?id=(?P<desc>\d*)" title="View Torrent">(?P<name>[^<]*)</a>.*'.
				'<a id="download_torrent" href="torrents\.php\?action=ddl&amp;gid=.*.&amp;id=(?P<id>\d*)" title="Download">DL</a>.*'.
				'Uploaded (?P<date>[^<]*)</em>.*'.
				'<li class="torrent_size"><a href="torrents\.php[^>]*>(?P<size>[^<]*)</a></li>.*'.
				'<li class="torrent_seeders"><a href="torrents\.php[^>]*>(?P<seeds>.*)</li>.*'.
				'<li class="torrent_leechers"><a href="torrents\.php[^>]*>(?P<leech>.*)</li>'.
				'`siU', $cli->results, $matches);
			if($res!==false)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $url.'/torrents.php?action=download&id='.self::removeTags($matches["id"][$i]);
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["desc"] = $url."/torrents.php?id=".self::removeTags($matches["desc"][$i]);
						$item["size"] = self::formatSize($matches["size"][$i]);
						$item["time"] = strtotime(self::removeTags($matches["date"][$i]));
						$item["seeds"] = intval(self::removeTags($matches["seeds"][$i]));
						$item["peers"] = intval(self::removeTags($matches["leech"][$i]));
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["cat"] = self::removeTags($matches["cat"][$i]);
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
