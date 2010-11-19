<?php

class SumoTorrentEngine extends commonEngine
{
	public $defaults = array( "public"=>true, "page_size"=>100 );
	public $categories = array( 'all'=>'', 'Movies'=>'4', 'Music'=>'3', 'TV series'=>'9', 'Games'=>'2', 'Applications'=>'1', 
		'Handheld'=>'6', 'Anime'=>'8', 'Non-English'=>'7', 'No Category'=>'10000', 'XXX'=>'10', 'Assorted'=>'5' );

	public function getTorrent( $url )
	{
		$cli = $this->fetch( $url );
		if($cli)
		{
			$url = str_replace( "/download/", "/torrent_download/", $url );
			$cli->setcookies();
			$cli->fetchComplex(Snoopy::linkencode($url));
			if($cli->status>=200 && $cli->status<300)
			{
				$name = $cli->get_filename();
				if($name===false)
					$name = md5($url).".torrent";
				$name = getUniqueFilename(getUploadsPath()."/".$name);
				$f = @fopen($name,"w");
				if($f!==false)
				{
					@fwrite($f,$cli->results,strlen($cli->results));
					fclose($f);
					@chmod($name,0666);
					return($name);
				}
			}
		}
		return(false);
	}
	static protected function getInnerCategory($cat)
	{
		$categories = array( '4'=>'Movies', '3'=>'Music', '9'=>'TV series', '2'=>'Games', '1'=>'Applications', '6'=>'Handheld', '8'=>'Anime', '7'=>'Non-English', '10000'=>'No Category', '10'=>'XXX', '5'=>'Assorted' );
		return(array_key_exists($cat,$categories) ? $categories[$cat] : '');
	}
	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://torrents.sumotorrent.com';
		if($useGlobalCats)
			$categories = array( 'all'=>'', 'movies'=>'4', 'tv'=>'9', 'music'=>'3', 'games'=>'2', 'anime'=>'8', 'software'=>'1', 'pictures'=>'5', 'books'=>'5' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];

		$maxPage = 10;
		for($pg = 0; $pg<$maxPage; $pg++)
		{
			$cli = $this->fetch( $url.'/searchResult.php?search='.$what.'&lngMainCat='.$cat.'&start='.$pg.'&order=seeders&by=down' );
			if($cli==false || (strpos($cli->results, "<b>No torrents found</b>")!==false) ||
				!preg_match('/Showing results from <b>\d+<\/b> to <b>\d+<\/b> \((?P<cnt>\d+) total\)<\/div>/siU',$cli->results, $matches))
				break;
			$maxPage = ceil(intval($matches["cnt"])/100);

			$res = preg_match_all('/<td class="trow" align="center">(?P<date>.*)<\/td>.*'.
				'<td .*>.*<a href="http:\/\/torrents.sumotorrent.com\/en\/cat_(?P<cat>\d+)\.html"'.
				'.*<\/td>.*<td .*>.*<a href="http:\/\/torrents.sumotorrent.com\/en\/details\/(?P<desc>.*)".*">(?P<name>.*)<\/a>.*<\/td>.*'.
				'<a href="http:\/\/torrents.sumotorrent.com\/download\/(?P<link>.*)".*<\/td>.*<td .*>(?P<size>.*)<\/td>.*'.
				'<td .*>(?P<seeds>.*)<\/td>.*<td .*>(?P<leech>.*)<\/td>'.
				'/siU', $cli->results, $matches);

			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $url."/download/".$matches["link"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["desc"] = $url."/en/details/".$matches["desc"][$i];
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize(trim($matches["size"][$i]));
						$item["seeds"] = intval(self::removeTags($matches["seeds"][$i]));
						$item["peers"] = intval(self::removeTags($matches["leech"][$i]));
						$item["time"] = strtotime(trim(self::removeTags($matches["date"][$i])));
						$item["cat"] = self::getInnerCategory(trim($matches["cat"][$i]));
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