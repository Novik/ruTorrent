<?php

class HDTorrentsEngine extends commonEngine
{
       	public $defaults = array( "public"=>false, "page_size"=>15, "cookies"=>"hd-torrents.org|pass=XXX;uid=XXX;" );

	public $categories = array
	( 
		'all'=>'', 
		'Movie'=>'&category[]=1&category[]=2&category[]=5category[]=3category[]=63',
		'TV Show'=>'&category[]=59&category[]=60&category[]=30category[]=38',
		'Music'=>'&category[]=44&category[]=61&category[]=62&category[]=57&category[]=45',
		'XXX'=>'&category[]=58&category[]=48&category[]=47' 
	);

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://hd-torrents.org';
		if($useGlobalCats)
			$categories = array
			( 
				'all'=>'0', 
				'movies'=>'&category[]=1&category[]=2&category[]=5category[]=3category[]=63', 
				'tv'=>'&category[]=59&category[]=60&category[]=30category[]=38', 
				'music'=>'&category[]=44&category[]=61&category[]=62&category[]=57&category[]=45'
			);
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];

		for($pg = 0; $pg<11; $pg++)
		{
			$cli = $this->fetch( $url.'/torrents.php?active=1'.$cat.'&search='.$what.'&options=0&order=seeds&by=DESC&page='.$pg );
			if( ($cli==false) || (strpos($cli->results, ">No torrents here...</td>")!==false) ||
				(strpos($cli->results, ">Password:<")!==false))
				break;
			$result = $cli->results;
			$first = strpos($result, "<!-- Column Headers  -->");
			if($first!==false)
				$result = substr($result,$first);

			$res = preg_match_all('`<a href=torrents\.php\?category=\d+><img src=images/categories/.*alt="(?P<cat>.*)"/><\/td>.*'.
				'<A HREF="details.php\?id=(?P<id>.*)".*'.
				'nd\(\);">(?P<name>.*)</A>.*'.
				'<TD align="center" class="mainblockcontent".*'.
				'<TD align="center" class="mainblockcontent".*'.
				'<TD align="center" class="mainblockcontent".*'.
				'<TD align="center" class="mainblockcontent">(?P<date>.*)</td>.*'.
				'<TD align="center" class="mainblockcontent">(?P<size>.*)</td>.*'.
				'<td.*><a href="peers\.php.*>(?P<seeds>.*)</td>.*'.
				'<td.*><a href="peers\.php.*>(?P<leech>.*)</td>'.
				'`siU', $result, $matches);

			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $url."/download.php?id=".$matches["id"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/details.php?id=".$matches["id"][$i];
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize($matches["size"][$i]);
						$item["time"] = strtotime(str_replace("/", "-",self::removeTags($matches["date"][$i])));
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
