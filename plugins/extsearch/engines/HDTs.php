<?php

class HDTsEngine extends commonEngine
{
       	public $defaults = array( "public"=>false, "page_size"=>15, "cookies"=>"hdts.ru|pass=XXX;uid=XXX;" );

	public $categories = array
	( 
		'all'=>'', 
		'Movie(all)'=>'&category[]=1&category[]=2&category[]=5&category[]=3&category[]=63',
		'Movie(all-3D only)'=>'&category[]=1&category[]=2&category[]=5&category[]=3&category[]=63&3d=1',	
		'Movie/BluRay'=>'&category[]=1',
		'Movie/BluRay(3D only)'=>'&category[]=1&3d=1',
		'Movie/Remux'=>'&category[]=2',
		'Movie/Remux(3D only)'=>'&category[]=2&3d=1',
		'Movie/1080p/i'=>'&category[]=5',
		'Movie/1080p/i(3D only)'=>'&category[]=5&3d=1',
		'Movie/720p'=>'&category[]=3',
		'Movie/720p(3D only)'=>'&category[]=3&3d=1',
		'Movie/Audio Track'=>'&category[]=63',
		'TV Show(all)'=>'&category[]=59&category[]=60&category[]=30&category[]=38',
		'TV Show(all-3D only)'=>'&category[]=59&category[]=60&category[]=30&category[]=38&3d=1',
		'TV Show/Blu-ray'=>'&category[]=59',
		'TV Show/Blu-ray(3D only)'=>'&category[]=59&3d=1',
		'TV Show/Remux'=>'&category[]=60',
		'TV Show/Remux(3D only)'=>'&category[]=60&3d=1',
		'TV Show/1080p/i'=>'&category[]=30',
		'TV Show/1080p/i(3D only)'=>'&category[]=30&3d=1',	
		'TV Show/720p'=>'&category[]=38',
		'TV Show/720p(3D only)'=>'&category[]=38&3d=1',			
		'Music(all)'=>'&category[]=44&category[]=61&category[]=62&category[]=57&category[]=45',
		'Music(3D only)'=>'&category[]=44&category[]=61&category[]=62&category[]=57&category[]=45&3d=1',
		'Music/Album'=>'&category[]=44',
		'Music/Blu-Ray'=>'&category[]=61',
		'Music/Remux'=>'&category[]=62',
		'Music/1080p/i'=>'&category[]=57',
		'Music/720p'=>'&category[]=45',
		'XXX(all)'=>'&category[]=58&category[]=48&category[]=47', 
		'XXX(3D only)'=>'&category[]=58&category[]=48&category[]=47&3d=1', 
		'XXX/Blu-ray'=>'&category[]=58', 
		'XXX/1080p/i'=>'category[]=48', 
		'XXX/720p'=>'&category[]=47' 
	);

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://hdts.ru';
		if($useGlobalCats)
			$categories = array( 'all'=>'&category[]=0' );
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
