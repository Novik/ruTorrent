<?php

class PretoMeEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>25, "cookies"=>"pretome.info|pass=XXX;uid=XXX" );

	public $categories = array( 'all'=>'', 'Applications'=>'&cat[]=22', 'Ebooks'=>'&cat[]=27', 'Games'=>'&cat[]=4',
		'Miscellaneous'=>'&cat[]=31', 'Movies'=>'&cat[]=19', 'Music'=>'&cat[]=6',
		'TV'=>'&cat[]=7' );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://pretome.info';
		if($useGlobalCats)
			$categories = array( 'all'=>'0', 'movies'=>'&cat[]=19', 'tv'=>'&cat[]=7', 'music'=>'&cat[]=6', 'games'=>'&cat[]=4', 'software'=>'&cat[]=22', 'books'=>'&cat[]=27' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		for($pg = 0; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/browse.php?search='.$what.'&tags=&st=1&tf=all'.$cat.'&sort=7&type=d&page='.$pg );
			if( ($cli==false) || (strpos($cli->results, "<h2>No .torrents fit this filter criteria</h2>")!==false) ||
				(strpos($cli->results, '<input type="password" name="password"')!==false))
				break;

			$res = preg_match_all('/<img src="https:\/\/pretome\.info\/pic\/icons\/.*" title="(?P<cat>.*)".*\/><\/a>.*'.
				'href="details.php\?id=(?P<id>\d+)" title="(?P<name>.*)">.*'.
				'<td class="row3" style="text-align: center;"><a href="download\.php\/\d+\/(?P<tname>.*)">.*'.
				'<td class="row3" style="text-align: center;"><a href="details\.php\?id=\d+&files#files">.*'.
				'<td class="row3" style="text-align: center;"><a href="details\.php\?id=\d+#comments">.*'.
				'<td class="row3" style="text-align: center;">(?P<date>.*)<\/td>.*'.
				'<td class="row3" style="text-align: center;"><img src=.*'.
				'<td class="row3" style="text-align: center;">(?P<size>.*)<\/td>.*'.
				'<td class="row3" style="text-align: center;">.*'.
				'<td class="row3" style="text-align: center;">(?P<seeds>.*)<\/td>.*'.
				'<td class="row3" style="text-align: center;">(?P<leech>.*)<\/td>/siU', $cli->results, $matches);
			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $url."/download.php/".$matches["id"][$i]."/".$matches["tname"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/details.php?id=".$matches["id"][$i];
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize(str_replace("<br />"," ",$matches["size"][$i]));
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
