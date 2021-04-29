<?php

class ImmortalSeedEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>30, "auth"=>1 );

	public $categories = array(
		'All'=>'',
		'All Nuked'=>'&selectedcats2=3',
		'Anime'=>'&selectedcats2=32',
		'Apps'=>'&selectedcats2=23',
		'Audiobooks'=>'&selectedcats2=35',
		'Childrens/Cartoons'=>'&selectedcats2=31',
		'Documentary - HD'=>'&selectedcats2=54',
		'Documentary - SD'=>'&selectedcats2=53',
		'Ebooks'=>'&selectedcats2=22',
		'|-- Comics'=>'&selectedcats2=41',
		'|-- Magazines'=>'&selectedcats2=46',
		'Games'=>'&selectedcats2=25',
		'|-- Games Xbox'=>'&selectedcats2=29',
		'|-- Games-PC ISO'=>'&selectedcats2=26',
		'|-- Games-PC Rips'=>'&selectedcats2=27',
		'|-- Games-PSx'=>'&selectedcats2=28',
		'Mobile'=>'&selectedcats2=49',
		'|-- Android'=>'&selectedcats2=51',
		'|-- IOS'=>'&selectedcats2=50',
		'|-- Windows'=>'&selectedcats2=52',
		'Movies-4k'=>'&selectedcats2=59',
		'|-- Non-English'=>'&selectedcats2=60',
		'Movies-HD'=>'&selectedcats2=16',
		'|-- Non-English'=>'&selectedcats2=18',
		'Movies-Low Def'=>'&selectedcats2=17',
		'|-- Non-English'=>'&selectedcats2=34',
		'Movies-SD'=>'&selectedcats2=14',
		'|-- Non-English'=>'&selectedcats2=33',
		'Music'=>'&selectedcats2=30',
		'|-- FLAC'=>'&selectedcats2=37',
		'|-- MP3'=>'&selectedcats2=36',
		'|-- Other'=>'&selectedcats2=39',
		'|-- Video'=>'&selectedcats2=38',
		'Other'=>'&selectedcats2=45',
		'Sports Tv'=>'&selectedcats2=7',
		'|-- Fitness-Instructional'=>'&selectedcats2=44',
		'|-- Olympics'=>'&selectedcats2=58',
		'TV - 480p'=>'&selectedcats2=47',
		'TV - High Definition'=>'&selectedcats2=8',
		'TV SD - x264'=>'&selectedcats2=48',
		'TV SD - XviD'=>'&selectedcats2=9',
		'TV Season Packs - HD'=>'&selectedcats2=4',
		'TV Season Packs - SD'=>'&selectedcats2=6'
		);

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$url = 'https://immortalseed.me';
		$added = 0;
		if($useGlobalCats)
			$categories = array(
				'all'=>'',
				'movies'=>'&selectedcats2=59,16,17,14',
				'tv'=>'&selectedcats2=47,8,48,9,4,6',
				'music'=>'&selectedcats2=30',
				'games'=>'&selectedcats2=25',
				'anime'=>'&selectedcats2=32',
				'software'=>'&selectedcats2=23,49',
				'books'=>'&selectedcats2=35,22'
				);
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		for($pg = 1; $pg<11; $pg++)
		{
			$cli = $this->fetch( $url.'/browse.php?do=search&keywords='.$what.'&search_type=t_name'.$cat.'&sort=seeders&order=desc&page='.$pg );
			  if ($cli == false) {
	    $item = $this->getNewEntry();
	    $item["name"] = "Fetch Error";
	    $ret[""] = $item;
            return;
	}
						$res = preg_match_all('`<td align="center" style="width: 40px; height: 36px;" class="unsortable2">.*'.
				'<img src=".*" border="0" alt="(?P<cat>.*)" title=".*" width="40" height="36" loading="lazy" />.*'.
				'<div style="text-align:left; margin-top: 5px">(?P<name>.*)</div>.*'.
				'<div>.*</span>(?P<date>.*)</div>.*'.
				'<td .*>.*</td>.*'.
				'<td .*>.*</td>.*'.
				'<td .*>(?P<size>.*)</td>.*'.
				'<td .*>.*<a href=".*id=(?P<id>\d+)" title=".*">.*</td>.*'.
				'<td .*>(?P<seeds>.*)</td>.*'.
				'<td .*>(?P<leech>.*)</td>'.
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
						$item["size"] = self::formatSize(trim($matches["size"][$i]));
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
