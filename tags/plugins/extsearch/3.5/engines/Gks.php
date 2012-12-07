<?php

class GksEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>35, "cookies"=>"gks.gs|uid=XXX;pw=XXX" );
	public $categories = array( 
		'all'=>'&category=0', 
		'Windows' => '&category=3', 
		'Mac' => '&category=4',
		'DVDRip/BDRip' => '&category=5', 
		'DVDRip/BDRip VOSTFR' => '&category=6', 
		'Emissions TV' => '&category=7',
		'Docs' => '&category=8', 
		'Docs HD' => '&category=9', 
		'TV PACK' => '&category=10', 
		'TV VOSTFR' => '&category=11',
		'TV VF' => '&category=12', 
		'TV HD VOSTFR' => '&category=13', 
		'TV HD VF' => '&category=14',
		'HD 720p' => '&category=15', 
		'HD 1080p' => '&category=16', 
		'Full BluRay' => '&category=17',
		'Divers' => '&category=18', 
		'DVDR' => '&category=19', 
		'DVDR Series' => '&category=20', 
		'Anime' => '&category=21',
		'TV VO' => '&category=22', 
		'Concerts' => '&category=23', 
		'eBooks' => '&category=24', 
		'Sport' => '&category=28', 
		'PC Games' => '&category=29', 
		'Nintendo DS' => '&category=30', 
		'Wii' => '&category=31',
		'Xbox 360' => '&category=32', 
		'PSP' => '&category=34', 
		'PSX/PS2/PS3' => '&category=38',
		'Flac' => '&category=39'
	);

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://gks.gs';
		if($useGlobalCats)
			$categories = array( 'all'=>'&category=0', 'movies'=>'&category=5&category=6&category=15&category=16&category=17&category=19', 
				'tv'=>'&category=7&category=8&category=9&category=10&category=11&category=12&category=13&category=14&category=20&category=22&category=23&category=28', 'music'=>'&category=39', 
				'games'=>'&category=29&category=30&category=31&category=32&category=34&category=38', 
				'anime'=>'&category=21', 'software'=>'&category=3&category=4', 'books'=>'&category=24' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		for($pg = 0; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/sphinx/?q='.$what.$cat.'&sort=id&order=desc&page='.$pg );
			if( ($cli===false) || (strpos($cli->results, "Votre Recherche n'a retourn&eacute; aucun r&eacute;sultat.<br />")!==false)
				|| (strpos($cli->results, '<h1>Wh0 Loves You ?</h1>')!==false))
				break;
			$res = preg_match_all('`<a class=".*" href="/browse/\?cat=.*">.*'.
					'<a title="(?P<name>.*)" href="(?P<desc>.*)">.*'.
					'<a href="(?P<tname>[^"]*)">.*'.
					'<td class="size_torrent_\d">(?P<size>.*)</td>.*'.
					'<td class="seed_torrent_\d">(?P<seeds>.*)</td>.*'.
					'<td class="leech_torrent_\d">(?P<leech>.*)</td>'.
					'`siU', $cli->results, $matches);
			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $url.$matches["tname"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["desc"] = $url.$matches["desc"][$i];
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize(trim(str_replace("o","B",$matches["size"][$i])));
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
