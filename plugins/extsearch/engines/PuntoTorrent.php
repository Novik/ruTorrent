<?php

class PuntoTorrentEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>15, "cookies"=>"xbt.puntotorrent.com|uid=XXX;pass=XXX" );
	public $categories = array(
		'all'=>'&category=0',
		'DVD/Animación'=>"&category=37",
		'DVD/Deportes'=>"&category=38",
		'DVD/Documentales'=>"&category=39",
		'DVD/Películas'=>"&category=40",
		'DVD/Series'=>"&category=41",
		'DVD/Adulto (+18)'=>"&category=42",
		'DVD/Musica'=>"&category=43",
		'DIVX/Series'=>"&category=14",
		'DIVX/Animación'=>"&category=45",
		'DIVX/Deportes'=>"&category=46",
		'DIVX/Documentales'=>"&category=47",
		'DIVX/Estrenos'=>"&category=33",
		'DIVX/Películas'=>"&category=48",
		'DIVX/Estrenos Baja Calidad'=>"&category=114",
		'DIVX/Adulto (+18)'=>"&category=12",
		'DIVX/Música'=>"&category=51",
		'HD/Animación'=>"&category=53",
		'HD/Deportes'=>"&category=54",
		'HD/Documentales'=>"&category=55",
		'HD/Películas'=>"&category=56",
		'HD/HD Xbox360'=>"&category=57",
		'HD/HD PS3'=>"&category=58",
		'HD/Series'=>"&category=59",
		'HD/Adulto (+18)'=>"&category=63",
		'HD/Música'=>"&category=61",
		'Música/Mp3'=>"&category=69",
		'Música/FLAC'=>"&category=70",
		'Música/Otros formatos de audio'=>"&category=71",
		'Juegos/Microsoft'=>"&category=25",
		'Juegos/Sony'=>"&category=26",
		'Juegos/PC'=>"&category=27",
		'Juegos/Nintendo'=>"&category=28",
		'Juegos/Otros'=>"&category=34",
		'Juegos/Emuladores'=>"&category=113",
		'Otros/Varios'=>"&category=72",
		'Otros/eBooks'=>"&category=6",
		'Otros/Hentai'=>"&category=102",
		'Otros formatos de vídeo/VCD'=>"&category=17",
		'Otros formatos de vídeo/Estrenos VCD'=>"&category=78",
		'Software/Windows'=>"&category=74",
		'Software/Linux'=>"&category=75",
		'Software/MAC'=>"&category=76",
		'Software/Otras Plataformas'=>"&category=77",
		'Software/Android'=>"&category=115",
		'Disp. Portátiles/Series'=>"&category=96",
		'Disp. Portátiles/Animación'=>"&category=92",
		'Disp. Portátiles/Deportes'=>"&category=93",
		'Disp. Portátiles/Documentales'=>"&category=94",
		'Disp. Portátiles/Películas'=>"&category=95",
		'Disp. Portátiles/Series'=>"&category=97",
		'Disp. Portátiles/Adulto XXX (+18)'=>"&category=98",
		'Disp. Portátiles/Música'=>"&category=99",
		'Disp. Portátiles/Estrenos'=>"&category=100",
		'Consolas Portátiles/Animación'=>"&category=84",
		'Consolas Portátiles/Deportes'=>"&category=85",
		'Consolas Portátiles/Documentales'=>"&category=86",
		'Consolas Portátiles/Películas'=>"&category=87",
		'Consolas Portátiles/Series'=>"&category=88",
		'Consolas Portátiles/Adulto XXX (+18)'=>"&category=89",
		'Consolas Portátiles/Música'=>"&category=90",
		'Consolas Portátiles/Estrenos'=>"&category=91",
		'Otros Dispositivos'=>"&category=101",
		'HDRip/Animación'=>"&category=106",
		'HDRip/Deportes'=>"&category=107",
		'HDRip/Documentales'=>"&category=108",
		'HDRip/Películas'=>"&category=109",
		'HDRip/Series'=>"&category=110",
		'HDRip/Adulto (+18)'=>"&category=112",
		'HDRip/Música'=>"&category=111",
		'3D/Animación 3D'=>"&category=117",
		'3D/Deportes 3D'=>"&category=118",
		'3D/Documentales 3D'=>"&category=119",
		'3D/Películas 3D'=>"&category=120",
		'3D/Adulto XXX (+18) 3D'=>"&category=121",
		'3D/Música 3D'=>"&category=122",
		'GOLD'=>"&category=0&active=3",
		'SILVER'=>"&category=0&active=4"
	);

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://xbt.puntotorrent.com';

		if($useGlobalCats)
			$categories = array( 'all'=>'&category=0' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];

		for($pg = 1; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/index.php?page=torrents&search='.$what.'&active=1&category='.$cat.'&pages='.$pg );
			
			if( ($cli==false) )		break;

			$res = preg_match_all('`<td align="center".*<img src="http://xbt.puntotorrent.com/style/xbtit_default/images/categories/.*" border="0"  alt="(?P<cat>[^"]*)".*'.
				'<td align="left" class="lista" style="white-space:wrap;padding-left:10px;"><a class="enlace" id="\d*" href="javascript:void\(\);" >(?P<name>.*)</a>.*'.
				'href="download\.php(?P<link>[^"]*)">.*'.
				'<td align="center" width="85" class="lista" style="white-space:wrap; text-align:center;">(?P<date>.*)</td>.*'.
				'<td align="center" width="80" class="lista" style="white-space:wrap; text-align:center;">(?P<size>.*)</td>.*'.
				'<td.*>.*</td>.*'.
				'<td align="center" width="30".*id=(?P<id>.*)".*title="Click aqui para ver los detalles de los peers">(?P<seeds>.*)</a></td>.*'.
				'<td align="center" width="30".*title="Click aqui para ver los detalles de los peers">(?P<leech>.*)</a></td>.*'.
				'`siU', $cli->results, $matches);

			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $url."/download.php".$matches["link"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/index.php?page=torrent-details&id=".$matches["id"][$i];
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize(trim(str_replace("<br>"," ",$matches["size"][$i])));
						$item["time"] = strtotime(self::removeTags(trim(str_replace("<br />"," ",str_replace("/",".",$matches["date"][$i])))));
						$item["seeds"] = intval(trim(self::removeTags($matches["seeds"][$i])));
						$item["peers"] = intval(trim(self::removeTags($matches["leech"][$i])));
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
