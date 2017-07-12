<?php

class IPTorrentsEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>50, "cookies"=>"iptorrents.com|pass=XXX;uid=XXX;" );
	public $categories = array( 'all'=>'',
		'Movies'=>'72', 'TV'=>'73', 'Games'=>'74', 'Music'=>'75', 'Miscellaneous'=>'76', 'XXX'=>'88' );

	protected static $seconds = array
	(
		'minutes'	=>60,
		'hours'		=>3600,
		'days'		=>86400,
		'weeks'		=>604800,
		'months'	=>2592000,
		'years'		=>31536000,
	);

	protected static function getTime( $now, $ago, $unit )
	{
		$delta = (array_key_exists($unit,self::$seconds) ? self::$seconds[$unit] : 0);
		return( $now-($ago*$delta) );
	}

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://iptorrents.com';
		if($useGlobalCats)
			$categories = array( 'all'=>'',
				'movies'=>'72', 'tv'=>'73', 'music'=>'75', 'games'=>'74',
				'anime'=>'60', 'software'=>'1;86', 'pictures'=>'36', 'books'=>'35;64;94' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		for($pg = 1; $pg<11; $pg++)
		{
			$cli = $this->fetch( $url.'/t?'.$cat.';o=seeders;q='.$what.';p='.$pg );
			if( ($cli==false) || (strpos($cli->results, ">No Torrents Found!<")!==false) ||
				(strpos($cli->results, 'name="password')!==false))
				break;

			$res = preg_match_all('/ac>(?P<cat>[A-Za-z]{2})<.*'.
				'href="\/details\.php\?id=(?P<id>\d+)">(?P<name>.*)<.*'.
				'ctime">(.* \| )?(?P<ago>[0-9\.]+) (?P<unit>(minutes|hours|days|weeks|months|years)).*'.
				'href="\/download\.php\/\d+\/(?P<tname>.*)".*'.
				'<td .*>.*<td .*>(?P<size>.*)<.*'.
				'seeders">(?P<seeds>.*)<.*'.
				'leechers">(?P<leech>.*)<'.
				'/siU', $cli->results, $matches);

			if($res)
			{
				$now = time();
				for($i=0; $i<$res; $i++)
				{
					$link = $url."/download.php/".$matches["id"][$i]."/".urlencode($matches["tname"][$i]);
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/details.php?id=".$matches["id"][$i];
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize($matches["size"][$i]);
						$item["time"] = self::getTime( $now, $matches["ago"][$i], $matches["unit"][$i] );
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
