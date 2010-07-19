<?php

class HDTorrentsEngine extends commonEngine
{
       	public $defaults = array( "public"=>false, "page_size"=>15, "cookies"=>"hd-torrents.org|pass=XXX;uid=XXX;" );

	public $categories = array( 'all'=>'0', 'Blu-Ray'=>'1', 'HD-DVD'=>'6', 'Remux'=>'55', 'BD-25'=>'56', 
		'Movies 720p'=>'3', 'Movies 1080p'=>'5', 'HDTV 720p'=>'38', 'HDTV 1080p'=>'30', 'Doc 720p'=>'29', 
		'Doc 1080p'=>'34', 'Anime 720p'=>'32', 'Anime 1080p'=>'41', 'HQ Audio'=>'44', 'HQ Videos'=>'45', 
		'XXX 720p'=>'47', 'XXX 1080p'=>'48', 'HQ-Images'=>'50', 'Other'=>'51', 'Software'=>'53', 'Trailers'=>'54' ); 

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://hd-torrents.org';
		if($useGlobalCats)
			$categories = array( 'all'=>'0', 'music'=>'44', 'software'=>'53', 'pictures'=>'50' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];

		for($pg = 0; $pg<11; $pg++)
		{
			$cli = $this->fetch( $url.'/torrents.php?active=1search='.$what.'&order=seeds&by=DESC&page='.$pg.'&category='.$cat );
			if( ($cli==false) || (strpos($cli->results, ">No torrents here...</td>")!==false) ||
				(strpos($cli->results, ">Password:</td>")!==false))
				break;
			$result = $cli->results;
			$first = strpos($result, "<!-- Column Headers  -->");
			if($first!==false)
				$result = substr($result,$first);

			$res = preg_match_all('/<img src=images\/categories\/.*alt="(?P<cat>.*)"\/><\/td>.*'.
				'<TD align="left" class="lista">&nbsp;&nbsp;<A HREF="details.php\?id=(?P<id>.*)".*'.
				'nd();">(?P<name>.*)<\/A>.*<\/td>.*<TD align="center" class="header">.*<\/td>.*<TD align="center" class="lista">.*<\/td>.*<td align="center" class="lista">(?P<date>.*)<\/td>.*'.
				'<td align="center" class="lista">(?P<size>.*)<\/td>*.'.
				'<td align="center" class="lista">.*<\/td>*.'.
				'<td .*>(?P<seeds>.*)<\/td>*.'.
				'<td .*>(?P<peers>.*)<\/td>/siU', $result, $matches);

			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $url."/download.php?id=".$matches["id"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::formatSize($matches["cat"][$i]);
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