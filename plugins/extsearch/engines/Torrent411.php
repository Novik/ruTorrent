<?php

class Torrent411Engine extends commonEngine
{
       	public $defaults = array( "public"=>false, "page_size"=>50, "cookies"=>"www.torrent411.com|uid=XXX;pass=XXX;authKey=XXX;" );

	public $categories = array( 'all'=>'0', 'Android: Tous'=>'113', 'Anime: Tous'=>'9', 'Applications: Linux'=>'49', 'Applications: Mac'=>'50', 'Applications: Windows'=>'3',
		'Cellulaires: Autres'=>'96', 'Cellulaires: Jeux'=>'97', 'Cellulaires: Sonneries'=>'98', 'Consoles: DS'=>'54', 'Consoles: DS (Films)'=>'109', 
		'Consoles: Émulation'=>'55', 'Consoles: PSP'=>'56', 'Consoles: PSP  (Films)'=>'107', 'Consoles: PS1'=>'57', 'Consoles: PS2'=>'58', 
		'Consoles: PS3'=>'59', 'Consoles: Wii'=>'60', 'Consoles: Xbox'=>'61', 'Consoles: Xbox360'=>'62', 'Documentaires: Tous'=>'34', 'Ebooks: Audio'=>'64',
		'Ebooks: Autres'=>'65', 'Ebooks: BD'=>'66', 'Événements: Autres'=>'99', 'Événements: Concerts'=>'100', 'Événements: Humour'=>'101', 'Événements: Sports'=>'102',
		'Films: Autres'=>'67', 'Films: CAM/TS'=>'46', 'Films: DVD-R'=>'5', 'Films: DVD-Rip'=>'30', 'Films: DVD-SCR/R1/R5'=>'43', 'Films: HD-R'=>'47',
		'Films: HD-Rip'=>'77', 'Films: SVCD/VCD'=>'78', 'Jeux: Mac'=>'104', 'Jeux: PC'=>'1', 'iPad: Tous'=>'111', 'iPhone: Applications'=>'80',
		'iPhone: Cartes GPS'=>'81', 'iPhone: Sonneries'=>'82', 'iPod: Applications'=>'83', 'iPod: Films'=>'84', 'iPod: Jeux'=>'85', 'iPod: Musique'=>'86',
		'iPod: Vidéos'=>'87', 'iPod: Séries-Télé'=>'88', 'Musique: FLAC'=>'110', 'Musique: Karaoké'=>'89', 'Musique: Mp3'=>'6', 'Musique: Vidéos-Clips'=>'38',
		'Séries-Télé: DVD-R'=>'91', 'Séries-Télé: DVD-Rip'=>'92', 'Séries-Télé: HD-R'=>'93', 'Séries-Télé: HD-Rip'=>'94', 'Séries-Télé: TV-Rip'=>'95', 'Windows Mobile: Tous'=>'114',
		'XXX: BD / Ebooks'=>'106', 'XXX: Films'=>'103', 'XXX: Jeux'=>'105'
 ); 

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://www.torrent411.com';
		if($useGlobalCats)
			$categories = array( 'all'=>'0', 'anime'=>'9' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];

		for($pg = 0; $pg<11; $pg++)
		{
			$cli = $this->fetch( $url.'/search/?search='.$what.'&sort=6&type=desc&page='.$pg.'&cat='.$cat );
			if( ($cli==false) || (strpos($cli->results, "<br>Aucun .torrents n'as encore été uploadé.")!==false))
				break;

			$res = preg_match_all('/<tr>.*<td class=ttable_col1 align=center><a href="\.\/browse\.php\?cat=\d+"><img.*alt="(?P<cat>.*)" \/><\/a><\/td>.*'.
				'<td class=ttable_col2>.*<b>(?P<name>.*)<\/b><\/a>.*'.
				'<b>Date Added:<\/b><\/td>.*<td>(?P<date>.*)<\/td>.*'.
				'<a href="torrents-details.php?id=(?P<id>.*)#startcomments.*'.
				'<td class=\'ttable_col2 tailleStyle\' align=center>(?P<size>.*)<\/td>.*'.
				'<td class=\'ttable_col2 seedersStyle\' align=center>(?P<seeds>.*)<\/td>.*'
				'<td class=\'ttable_col1 leechersStyle\' align=center>(?P<leech>.*)<\/td><\/tr>/siU', $result, $matches);
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
						$item["time"] = strtotime(str_replace("&nbsp;at&nbsp;", " ",self::removeTags($matches["date"][$i])));
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