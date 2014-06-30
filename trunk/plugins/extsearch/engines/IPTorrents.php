<?php

class IPTorrentsEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>35, "cookies"=>"www.iptorrents.com|pass=XXX;uid=XXX" );
	public $categories = array( 'all'=>'',
		'Movies'=>'&l72=1', 'TV'=>'&l73=1', 'Games'=>'&l74=1', 'Music'=>'&l75=1', 'Miscellaneous'=>'&l76=1', 'XXX'=>'&l88=1' );

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
		return( $now-$delta );
	}		
	
	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://www.iptorrents.com';
		if($useGlobalCats)
			$categories = array( 'all'=>'', 
				'movies'=>'&l72=1', 'tv'=>'&l73=1', 'music'=>'&l75=1', 'games'=>'&l74=1', 
				'anime'=>'&l60=1', 'software'=>'&l1=1&l86=1', 'pictures'=>'&l36=1', 'books'=>'&l35=1&l64=1' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		for($pg = 1; $pg<11; $pg++)
		{
			$cli = $this->fetch( $url.'/torrents/?'.$cat.'o=seeders;q='.$what.';qf=ti;p='.$pg );
			if( ($cli==false) || (strpos($cli->results, ">Nothing found!<")!==false) ||
				(strpos($cli->results, ">Password:<")!==false))
				break;

			$res = preg_match_all('`'.
				'<img class=".*" width="\d+" height="\d+" src=".*" alt="(?P<cat>.*)"></a>.*'.
				' href="/details\.php\?id=(?P<id>\d+)">(?P<name>.*)</a>.*'.
				't_ctime">(.* \| )?(?P<ago>[0-9\.]+) (?P<unit>(minutes|hours|days|weeks|months|years)) ago( by .*|)</div>.*'.
				'<td .*>.*href="/download\.php/\d+\/(?P<tname>.*)".*</a></td>'.
				'<td .*>.*</td><td .*>(?P<size>.*)</td><td .*>.*</td>'.
				'<td class="ac t_seeders">(?P<seeds>.*)</td>'.
				'<td class="ac t_leechers">(?P<leech>.*)</td>'.				
				'`siU', $cli->results, $matches);

			if($res)
			{
				$now = time();
				for($i=0; $i<$res; $i++)
				{
					$link = $url."/download.php/".$matches["id"][$i]."/".$matches["tname"][$i];
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
