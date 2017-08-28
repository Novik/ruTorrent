<?php

class Torrent411Engine extends commonEngine
{
	public $defaults = array( "public"=>true, "page_size"=>20 );

	public $categories = array(
			'Tout' => '',
			'|--Films' => '&category=1',
			'|--F--HD 720p' => '&subcategory=100',
			'|--F--HD 1080p' => '&subcategory=101',
			'|--F--HD 4k' => '&subcategory=102',
			'|--F--3D' => '&subcategory=103',
			'|--F--VF' => '&subcategory=104',
			'|--F--VOSTFR' => '&subcategory=105',
			'|--F--VO' => '&subcategory=106',
			'|--F--TRUEFRENCH' => '&subcategory=107',
			'|--F--MULTI' => '&subcategory=108',
			'|--F--Animation' => '&subcategory=110',
			'|--F--VFQ' => '&subcategory=111',
			'|--F--DVDRiP' => '&subcategory=112',
			'|--F--Autres' => '&subcategory=199',
			'|--Séries' => '&category=2',
			'|--S--HD 720p' => '&subcategory=200',
			'|--S--HD 1080p' => '&subcategory=201',
			'|--S--HD 4k' => '&subcategory=202',
			'|--S--3D' => '&subcategory=203',
			'|--S--VF' => '&subcategory=204',
			'|--S--VOSTFR' => '&subcategory=205',
			'|--S--VO' => '&subcategory=206',
			'|--S--MULTI' => '&subcategory=207',
			'|--S--Animation' => '&subcategory=208',
			'|--S--Émissions' => '&subcategory=209',
			'|--S--VFQ' => '&subcategory=211',
			'|--S--Autres' => '&subcategory=299',
			'|--Animes' => '&category=3',
			'|--A--HD 720p' => '&subcategory=300',
			'|--A--HD 1080p' => '&subcategory=301',
			'|--A--VF' => '&subcategory=302',
			'|--A--VOSTFR' => '&subcategory=303',
			'|--A--VO' => '&subcategory=304',
			'|--A--Autres' => '&subcategory=305',
			'|--A--MULTI' => '&subcategory=306',
			'|--Musique' => '&category=4',
			'|--M--FLAC' => '&subcategory=401',
			'|--M--MP3' => '&subcategory=402',
			'|--M--Wav' => '&subcategory=403',
			'|--M--Autres' => '&subcategory=404',
			'|--Ebooks' => '&category=5',
			'|--E--Ebook en Français' => '&subcategory=501',
			'|--E--Ebook en Anglais' => '&subcategory=502',
			'|--E--Livres Audio en Français' => '&subcategory=503',
			'|--E--Livres Audio en Anglais' => '&subcategory=504',
			'|--E--BD' => '&subcategory=505',
			'|--E--Comics' => '&subcategory=506',
			'|--E--Mangas' => '&subcategory=507',
			'|--E--Magazine' => '&subcategory=508',
			'|--E--Autres' => '&subcategory=599',
			'|--Logiciels' => '&category=6',
			'|--L--PC' => '&subcategory=600',
			'|--L--Mac' => '&subcategory=601',
			'|--L--Linux' => '&subcategory=602',
			'|--L--iPhone' => '&subcategory=603',
			'|--L--Android' => '&subcategory=604',
			'|--L--VST' => '&subcategory=605',
			'|--L--Autres' => '&subcategory=699',
			'|--Jeux' => '&category=7',
			'|--J--Consoles' => '&subcategory=700',
			'|--J--PC' => '&subcategory=701',
			'|--J--Linux' => '&subcategory=702',
			'|--J--Nintendo Switch' => '&subcategory=703',
			'|--J--Xbox One' => '&subcategory=704',
			'|--J--Playstation 4' => '&subcategory=705',
			'|--J--Nintendo 3DS' => '&subcategory=706',
			'|--J--Playstation Vita' => '&subcategory=707',
			'|--J--Wii U' => '&subcategory=708',
			'|--J--Wii' => '&subcategory=709',
			'|--J--Nintendo DS' => '&subcategory=710',
			'|--J--Xbox 360' => '&subcategory=711',
			'|--J--Playstation 3' => '&subcategory=712',
			'|--J--PSP' => '&subcategory=713',
			'|--J--ROMs' => '&subcategory=714',
			'|--J--Playstation 2' => '&subcategory=715',
			'|--J--Playstation 1' => '&subcategory=716',
			'|--J--iPhone' => '&subcategory=717',
			'|--J--Android' => '&subcategory=718',
			'|--J--Autres' => '&subcategory=799',
			'|--Documentaires' => '&category=8',
			'|--D--HD 720p' => '&subcategory=800',
			'|--D--HD 1080p' => 'subcategory=801',
			'|--D--HD 4k' => '&subcategory=802',
			'|--D--DVDRIP' => '&subcategory=803',
			'|--D--VF' => '&subcategory=804',
			'|--D--VFQ' => '&subcategory=805',
			'|--D--VOSTFR' => '&subcategory=806',
			'|--D--VO' => '&subcategory=807',
			'|--D--TRUEFRENCH' => '&subcategory=808',
			'|--D--MULTI' => '&subcategory=809',
			'|--D--3D' => '&subcategory=810',
			'|--D--Documentaire animé' => '&subcategory=811',
			'|--D--Autres' => '&subcategory=899',
			'|--XXX' => '&category=9',
			'|--X--Animation' => '&subcategory=901',
			'|--X--Hentai' => '&subcategory=902',
			'|--X--Français' => '&subcategory=903',
			'|--X--Hétéro' => '&subcategory=904',
			'|--X--Gay' => '&subcategory=905',
			'|--X--Shemale' => '&subcategory=906',
			'|--X--Autres' => '&subcategory=999'
			);


	protected static $seconds = array
	(
		'seconde'	=>1,
		'minute'	=>60,
		'heure'		=>3600,
		'jour'		=>86400,
		'semaine'	=>604800,
		'mois'		=>2592000,
		'an'		=>31536000
	);

	protected static function getTime( $now, $ago, $unit )
	{
		$delta = (array_key_exists($unit,self::$seconds) ? self::$seconds[$unit] : 0);
		return( $now-($ago*$delta) );
	}

	private $category_mapping = array(
			'1' => 'Films',
			'2' => 'Séries',
			'3' => 'Animes',
			'4' => 'Musique',
			'5' => 'Ebooks',
			'6' => 'Logiciels',
			'7' => 'Jeux',
			'8' => 'Documentaires',
			'9' => 'XXX'
			);

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://t411.si';
		if($useGlobalCats)
			$categories = array( 'all'=>'', 'movies'=>"&category=1", 'music'=>"&category=4", 'games'=>"&category=7",
					'anime'=>"&category=3", 'software'=>"&category=6", 'books'=>"&category=5" );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		for($pg = 1; $pg<11; $pg++)
		{
			$cli = $this->fetch( $url.'/torrents/search/?search='.$what.$cat.'&sortby=seeds&sort=desc&page='.$pg );
			if( ($cli==false) || (strpos($cli->results, 'class="m-cat"')===false) )
				break;

			$res = preg_match_all('`class="m-cat"><a href="/torrents/search/.*category=(?P<catid>\d)">.*'.
				'<a href="(?P<desc>.*)">(?P<name>.*)</a>.*'.
				'<span>.*</span>.*'.
				'<span>(?P<ago>\d+) (?P<unit>(seconde|minute|heure|jour|semaine|mois|an)).*</span>.*'.
				'<span>(?P<size>.*)</span>.*'.
				'<span.*>(?P<seeder>.*)</span>.*'.
				'<span.*>(?P<leecher>.*)</span>'.
				'`siU', $cli->results, $matches);

			if($res)
			{
				$now = time();
				for($i = 0; $i < $res; $i++)
				{
					// Download link not working:
					// Rquires "hash-info" - not "torrent id" - of the torrent
					// Not available in torrents search page
					$name = self::removeTags(trim($matches["name"][$i]));
					$id = explode("/",$matches["desc"][$i])[2];
					$link = $url."/telecharger-torrent/".$id."/".$name.".torrent";
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = $this->getCategoryName($matches["catid"][$i]);
						$item["desc"] = $url.$matches["desc"][$i];
						$item["name"] = $name;
						$item["size"] = self::formatSize($matches["size"][$i]);
						$item["time"] = self::getTime( $now, $matches["ago"][$i], $matches["unit"][$i] );
						$item["seeds"] = intval(self::removeTags($matches["seeder"][$i]));
						$item["peers"] = intval(self::removeTags($matches["leecher"][$i]));
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

	private function getCategoryName($catid)
	{
		if (array_key_exists($catid, $this->category_mapping)) {
			return $this->category_mapping[$catid];
		} else {
			return;
		}
	}
}
