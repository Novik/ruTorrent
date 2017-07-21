<?php

class EliteTrackerEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>30, "auth"=>1 );
	public $categories = array( 'Tout'=>'', "Animations/Animes" => "27", "Application" => "74", "Documentaires" => "38", "Ebooks" => "34",
				"Films" => "7", "HD" => "48", "Jeux Vidéo" => "15", "Musiques" => "23", "Séries" => "30", "Spectacles/Émissions" => "47", "Sport" => "35", "XXX" => "37" );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://elite-tracker.net';
		if($useGlobalCats)
			$categories = array( 'all'=>'', 'movies'=>"7", 'music'=>"23", 'games'=>"15", 'anime'=>"27", 'software'=>"74", 'books'=>"34" );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		for($pg = 1; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/browse.php?include_dead_torrents=no&keywords='.$what.'&search_type=t_name&category='.$cat.'&sort=seeders&order=DESC&page='.$pg );
			if( ($cli==false) || (strpos($cli->results, "<b>Aucun Resultat</b>")!==false) ||
				(strpos($cli->results, '<form method="post" action="takelogin.php">')!==false))
				break;

			$res = preg_match_all('/<td align="center" style=".*" class="unsortable2">.*'.
				'<a href=".*\.ts"><img src=".*\/images\/.*" border="0" alt="(?P<cat>.*)".*'.
				'<a href="https:\/\/elite-tracker\.net\/(?P<name>.*)-s-(?P<id>\d+)\.ts">.*'.
				'<\/span>(?P<date>.*)<\/div>.*'.
				'<\/a>.*<td align="center" class="unsortable2">(?P<size>.*)<\/td>.*'.
				'<span .*>.*<a href=".*>(?P<seeds>.*)<\/a>.*'.
				'<a href=".*>(?P<leech>.*)<\/a>'.
				'/siU', $cli->results, $matches);

			if($res)
			{
				for($i = 0; $i < $res; $i++)
				{
					$name = str_replace("_"," ",$matches["name"][$i]);
					$link = $url."/download.php?id=".$matches["id"][$i]."&type=ssl";
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags(str_replace("."," ",$matches["cat"][$i]));
						$item["desc"] = $url."/".$matches["name"][$i]."-s-".$matches["id"][$i].".ts";
						$item["name"] = $name;
						$item["size"] = self::formatSize(trim(str_replace("<br>"," ",$matches["size"][$i])));
						$item["time"] = strtotime(self::removeTags(str_replace("<br />"," ",$matches["date"][$i])));
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
