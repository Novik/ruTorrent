<?php

class ImmortalSeedEngine extends commonEngine
{
       	public $defaults = array( "public"=>false, "page_size"=>30, "auth"=>1 );
       	
	public $categories = array( 'all'=>'0', 'All Nuked'=>'3', 'Anime'=>'32', 'Apps'=>'23', 'Audiobooks'=>'35', 'Childrens/Cartoons'=>'31',
		'Ebooks'=>'22', '|-- Comics'=>'41', '|-- Magazines'=>'46', 'Games'=>'25', '|-- Games Xbox'=>'29', '|-- Games-PC ISO'=>'26',
		'|-- Games-PC Rips'=>'27', '|-- Games-PSx'=>'28', 'Movies-HD'=>'16', '|-- Non-English'=>'18', 'Movies-Low Def'=>'17',
		'|-- Non-English'=>'34', 'Movies-SD'=>'14', '|-- Non-English'=>'33', 'Music'=>'30', '|-- FLAC'=>'37', '|-- MP3'=>'36',
		'|-- Other'=>'39', '|-- Video'=>'38', 'Other'=>'45', 'Pre edit torrents'=>'5', 'Sports Tv'=>'7', '|-- Fitness-Instructional'=>'44',
		'TV - 480p'=>'47', 'TV - High Definition'=>'8', 'TV SD - x264'=>'48', 'TV SD - XviD'=>'9', 'TV Season Packs - HD'=>'4',
		'TV Season Packs - SD'=>'6' );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$url = 'https://immortalseed.tv';
		$added = 0;
		if($useGlobalCats)
			$categories = array( 'all'=>'0', 'music'=>'30', 'anime'=>'32', 'software'=>'23', 'pictures'=>'31', 'books'=>'22' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		for($pg = 1; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/browse.php?include_dead_torrents=no&keywords='.$what.'&search_type=t_name&page='.$pg.'&category='.$cat );
			if($cli==false || (strpos($cli->results, '<input type="password" name="password" class="inputPassword"')!==false)) 
				break;

			$res = preg_match_all('`<img src="'.$url.'/images/categories/.*" border="0" alt="(?P<cat>.*)".*'.
				'<div style="text-align:left; margin-top: 5px">(?P<name>.*)</div>.*'.
				'</span>\s*(?P<date>.*)\s*</div>.*'.
				'<a href="'.$url.'/download\.php\?id=(?P<id>\d+)">.*'.
				'<td align="center" class="unsortable2">\s*(?P<size>.*)\s*</td>.*'.
				'title="Seeders">(?P<seeds>.*)</a>.*'.
				'title="Leechers">(?P<leech>.*)</a>'.
				'`siU', $cli->results, $matches);

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
						$item["time"] = strtotime(self::removeTags(str_replace("-", "/",$matches["date"][$i])));
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
