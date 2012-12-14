<?php

class FrenchTorrentDBEngine extends commonEngine
{
    public $defaults = array( "public"=>false, "page_size"=>30, "auth"=>1 );
	public $categories = array( 
		'all' => '',
		//Films
        'DVDRIP' => '4',
        'DVDRIP VOSTFR' => '5',
        'DVD-R' => '7',
        'HD 720P' => '22',
        'HD 1080p' => '23',
        'Blu-ray Disc' => '29',
        '3D' => '31',
		//Séries
        'TV VF' => '12',
        'HD TV VF' => '36',
        'TV VOSTFR' => '13',
        'HD TV VOSTFR' => '37',
        'TV VO' => '39',
        'DVD-R TV' => '42',
        'TV PACK' => '43',
		//Jeux
        'JEUX' => '14',
        'JEUX PC' => '14&cid=102',
        'JEUX WII' => '14&cid=105',
        'JEUX XBOX' => '14&cid=108',
        'JEUX PSP' => '14&cid=107',
        'JEUX DS' => '14&cid=104',
        'JEUX PS3' => '14&cid=178',
		//Apps
        'APPS' => '15',
        'APPS WINDOWS' => '15&cid=111',
        'APPS MAC' => '15&cid=112',
        'APPS ANDROID' => '15&cid=177',
        'APPS IOS' => '15&cid=109',
        'APPS MOBILE' => '15&cid=110',
		//Divers
        'CAM/TS' => '1',
        'DVDSCR' => '2',
        'R5' => '6',
        'EBOOKS' => '17',
        'DIVERS' => '19',
        'MUSICS' => '16',
        'XXX' => '35',
		//Télé
        'DOCS' => '21',
        'SPORT' => '30',
        'SPECTACLE' => '10',
        'ANIME' => '20',
        'TELEVISION' => '44',
	);

    public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://www.frenchtorrentdb.com';
		$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		for($pg = 1; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/?section=TORRENTS&grid_id=&order_by=added&order=DESC&exact=1&name='.$what.'&parent_cat_id='.$cat.'&dead=&freeleech=&last_adv_cat_selected=&title=&desc=&genre=&page='.$pg.'&navname=#nav_');
			if(($cli==false) || (strpos($cli->results, "a retourné aucun résultat.</div>")!==false) ||
				(strpos($cli->results, 'type="password"')!==false))
				break;
            $res = preg_match_all('`<ul\s* class=".*"\s*>.*'.
				'<li class="torrents_name.*"><a href="\/\?section=INFOS&amp;hash=(?P<id>.*)\#FTD_MENU".*title="(?P<name>.*)">.*<\/a><\/li>.*'.
				'<li class="torrents_size.*">(?P<size>.*)<\/li>.*'.
				'<li class="torrents_seeders.*">(?P<seeds>.*)<\/li>.*'.
				'<li class="torrents_leechers.*">(?P<leech>.*)<\/li>.*'.
				'<a href="(?P<tname>[^"]*)">.*<\/ul>'.
				'`siU', $cli->results, $matches);
			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $url.$matches["tname"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["desc"] = $url."/?section=INFOS&hash=".$matches["id"][$i];
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize($matches["size"][$i]);
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
