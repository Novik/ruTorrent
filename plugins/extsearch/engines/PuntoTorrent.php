<?php

class PuntoTorrentEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>15, "cookies"=>"xbt.puntotorrent.com|uid=XXX;pass=XXX" );
	public $categories = array(
		'All'=>'&category=0',
		'DVD/Animación'=>'&category=37',
		'DVD/Deportes'=>'&category=38',
		'DVD/Documentales'=>'&category=39',
		'DVD/Películas'=>'&category=40',
		'DVD/Series'=>'&category=41',
		'DVD/Adulto (+18)'=>'&category=42',
		'DVD/Música'=>'&category=43',
		'XVID/Series'=>'&category=14',
		'XVID/Animación'=>'&category=45',
		'XVID/Deportes'=>'&category=46',
		'XVID/Documentales'=>'&category=47',
		'XVID/Estrenos'=>'&category=33',
		'XVID/Películas'=>'&category=48',
		'XVID/Estrenos BajaCalidad'=>'&category=114',
		'XVID/Adulto (+18)'=>'&category=12',
		'XVID/Música'=>'&category=51',
		'HD/Animación'=>'&category=53',
		'HD/Deportes'=>'&category=54',
		'HD/Documentales'=>'&category=55',
		'HD/BDrip/BDremux/FullBD'=>'&category=56',
		'HD/WEB-DL/Line Dubbed'=>'&category=58',
		'HD/Series'=>'&category=59',
		'HD/Adulto (+18)'=>'&category=63',
		'HD/Música'=>'&category=61',
		'UHD/Animación'=>'&category=136',
		'UHD/Deportes'=>'&category=137',
		'UHD/Documentales'=>'&category=138',
		'UHD/Películas'=>'&category=139',
		'UHD/Series'=>'&category=140',
		'UHD/Adulto (+18)'=>'&category=141',
		'Micro HD/Animación'=>'&category=126',
		'Micro HD/Deportes'=>'&category=127',
		'Micro HD/Documentales'=>'&category=128',
		'Micro HD/Películas'=>'&category=57',
		'Micro HD/Series'=>'&category=124',
		'Micro HD/Adulto (+18)'=>'&category=129',
		'Micro HD/Música'=>'&category=130',
		'Música/MP3'=>'&category=69',
		'Música/FLAC'=>'&category=70',
		'Música/Otros Formatos'=>'&category=71',
		'Juegos/Microsoft'=>'&category=25',
		'Juegos/Sony'=>'&category=26',
		'Juegos/PC'=>'&category=27',
		'Juegos/Nintendo'=>'&category=28',
		'Juegos/Otros'=>'&category=34',
		'Juegos/Emuladores/Otros'=>'&category=113',
		'Ebooks/Otro material/eBooks'=>'&category=6',
		'Ebooks/Otro material/Cómics'=>'&category=131',
		'Ebooks/Otro material/Manga'=>'&category=134',
		'Ebooks/Otro material/Revistas'=>'&category=132',
		'Ebooks/Otro material/Otros'=>'&category=72',
		'Ebooks/Otro material/Adulto +18'=>'&category=133',
		'Software/Windows'=>'&category=74',
		'Software/Linux'=>'&category=75',
		'Software/MAC'=>'&category=76',
		'Software/Otras Plataformas'=>'&category=77',
		'Software/Android'=>'&category=115',
		'Otros Formatos/Animación'=>'&category=92',
		'Otros Formatos/Deportes'=>'&category=93',
		'Otros Formatos/Documentales'=>'&category=94',
		'Otros Formatos/Películas'=>'&category=95',
		'Otros Formatos/Series'=>'&category=97',
		'Otros Formatos/Adulto (+18)'=>'&category=98',
		'Otros Formatos/Música'=>'&category=99',
		'Otros Formatos/Estrenos'=>'&category=100',
		'HDRip/Animación'=>'&category=106',
		'HDRip/Deportes'=>'&category=107',
		'HDRip/Documentales'=>'&category=108',
		'HDRip/Películas'=>'&category=109',
		'HDRip/Series'=>'&category=110',
		'HDRip/Adulto (+18)'=>'&category=112',
		'HDRip/Música'=>'&category=111',
		'3D/Animación'=>'&category=117',
		'3D/Deportes'=>'&category=118',
		'3D/Documentales'=>'&category=119',
		'3D/Películas'=>'&category=120',
		'3D/Adulto (+18)'=>'&category=121',
		'3D/Música'=>'&category=122'
	);

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://xbt.puntotorrent.com';

		if($useGlobalCats)
			$categories = array( 'all'=>'&category=0' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];

		for($pg = 1; $pg<11; $pg++)
		{
			$cli = $this->fetch( $url.'/index.php?page=torrents&active=1'.$cat.'&search='.$what.'&order=5&by=2&pages='.$pg );

			if( ($cli==false) )		break;

			$res = preg_match_all('`<img src="'.$url.'/style/xbtit_default/images/categories/.*" border="0"  alt="(?P<cat>.*)"/>.*'.
				'<td .*>(?P<name>.*)</td>.*'.
				'<td .*>.*</td>.*'.
				'<td .*><a href="(?P<link>.*)">.*</td>.*'.
				'<td .*>(?P<date>.*)</td>.*'.
				'<td .*>(?P<size>.*)</td>.*'.
				'<td .*>.*</td>.*'.
				'<td .*><a href=".*id=(?P<id>.*)" title=".*">(?P<seeds>.*)</a></td>.*'.
				'<td .*><a .*>(?P<leech>.*)</a></td>'.
				'`siU', $cli->results, $matches);

			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $url."/".$matches["link"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/index.php?page=torrent-details&id=".$matches["id"][$i];
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize(str_replace(",",".",str_replace(".","",$matches["size"][$i])));
						$item["time"] = strtotime(self::removeTags(str_replace("/","-",$matches["date"][$i])));
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
