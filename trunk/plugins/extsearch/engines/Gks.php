<?php

class GksEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>35, "cookies"=>"gks.gs|uid=XXX;pw=XXX" );
	public $categories = array( 'all'=>'&cat=0', 'Windows' => '&cat=3', 'Mac' => '&cat=4',
		'DVDRip/BDRip' => '&cat=5', 'DVDRip/BDRip VOSTFR' => '&cat=6', 'Emissions TV' => '&cat=7',
		'Docs' => '&cat=8', 'Docs HD' => '&cat=9', 'TV PACK' => '&cat=10', 'TV VOSTFR' => '&cat=11',
		'TV VF' => '&cat=12', 'TV HD VOSTFR' => '&cat=13', 'TV HD VF' => '&cat=14',
		'HD 720p' => '&cat=15', 'HD 1080p' => '&cat=16', 'Full BluRay' => '&cat=17',
		'Divers' => '&cat=18', 'DVDR' => '&cat=19', 'DVDR Series' => '&cat=20', 'Anime' => '&cat=21',
		'TV VO' => '&cat=22', 'Concerts' => '&cat=23', 'eBooks' => '&cat=24', 'Sport' => '&cat=28', 
		'PC Games' => '&cat=29', 'Nintendo DS' => '&cat=30', 'Wii' => '&cat=31',
		'Xbox 360' => '&cat=32', 'PSP' => '&cat=34', 'PSX/PS2/PS3' => '&cat=38');

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://gks.gs';
		if($useGlobalCats)
			$categories = array( 'all'=>'&cat=0', 'movies'=>'&cat=5&cat=6&cat=15&cat=16&cat=17&cat=19', 
				'tv'=>'&cat=7&cat=8&cat=9&cat=10&cat=11&cat=12&cat=13&cat=14&cat=20&cat=22&cat=23&cat=28', 'music'=>'&cat=6&cat=46&cat=29', 
				'games'=>'&cat=29&cat=30&cat=31&cat=32&cat=34&cat=38', 
				'anime'=>'&cat=21', 'software'=>'&cat=3&cat=4', 'books'=>'&cat=24' );
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
					'<!--<img src=".*" alt="(?P<cat>[^"]*)".*'.
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
						$item["cat"] = self::removeTags($matches["cat"][$i]);
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

?>