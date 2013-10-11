<?php

class TorrentPortalEngine extends commonEngine
{
       	public $defaults = array( "public"=>true, "page_size"=>50 );
	public $categories = array( 'all'=>'0', 'Games'=>'1', 'Movies'=>'2', 'TV'=>'3', 'Videos'=>'4', 
		'Apps'=>'5', 'Anime'=>'6', 'Audio'=>'7', 'Comics'=>'8', 'Unsorted'=>'9' );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://www.torrentportal.com';
		if($useGlobalCats)
			$categories = array( 'all'=>'0', 'movies'=>'2', 'tv'=>'3', 'music'=>'7', 'games'=>'1', 'anime'=>'6', 'software'=>'5', 'pictures'=>'8', 'books'=>'9' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		for($pg = 0; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/torrents-search.php?search='.$what.'&cat='.$cat.'&sort=seeders&d=desc&type=and&hidedead=on&page='.$pg );
			if( ($cli==false) || (strpos($cli->results, "<b>No Torrents Found</b>")!==false) )
				break;
			$res = preg_match_all('/<a href="\/download\/(?P<link>.*)">.*'.
				'<a href="\/browse\/[^"]*">(?P<cat>.*)<\/a>.*'.
				'<a href="\/details\/(?P<desc>.*)".*<b>(?P<name>.*)<\/b><\/a><\/td>'.
				'<td .*>.*<\/td><td .*>(?P<size>.*)<\/td><td .*>(?P<seeds>.*)<\/td><td .*>(?P<leech>.*)<\/td>/siU', $cli->results, $matches);
			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $url."/download/".$matches["link"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/details/".$matches["desc"][$i];
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize($matches["size"][$i]);
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
