<?php
class XthorEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>30, "auth"=>1 );
	public $categories = array(
		"Tout"=>"c0=1",
		"|--Films"=>"c118=1&c119=1&c107=1&c1=1&c2=1&c100=1&c4=1&c5=1&c7=1&c3=1&c6=1&c8=1&c122=1&c94=1&c95=1&c12=1&c31=1&c33=1&c125=1&c20=1&c9=1",
		"|--F--2160p/Bluray"=>"c118=1",
		"|--F--2160p/Remux"=>"c119=1",
		"|--F--2160p/x265"=>"c107=1",
		"|--F--1080p/BluRay"=>"c1=1",
		"|--F--1080p/Remux"=>"c2=1",
		"|--F--1080p/x265"=>"c100=1",
		"|--F--1080p/x264"=>"c4=1",
		"|--F--720p/x264"=>"c5=1",
		"|--F--SD/x264"=>"c7=1",
		"|--F--3D"=>"c3=1",
		"|--F--XviD"=>"c6=1",
		"|--F--DVD"=>"c8=1",
		"|--F--HDTV"=>"c122=1",
		"|--F--WEBDL"=>"c94=1",
		"|--F--WEBRiP"=>"c95=1",
		"|--F--Documentaire"=>"c12=1",
		"|--F--Animation"=>"c31=1",
		"|--F--Spectacle"=>"c33=1",
		"|--F--Sports"=>"c125=1",
		"|--F--Concerts, Clips"=>"c20=1",
		"|--F--VOSTFR"=>"c9=1",
		"|--Séries"=>"c104=1&c13=1&c15=1&c14=1&c98=1&c17=1&c16=1&c101=1&c32=1&c110=1&c123=1&c109=1&c34=1&c30=1",
		"|--S--BluRay"=>"c104=1",
		"|--S--Pack VF"=>"c13=1",
		"|--S--HD VF"=>"c15=1",
		"|--S--SD VF"=>"c14=1",
		"|--S--Pack VOSTFR"=>"c98=1",
		"|--S--HD VOSTFR"=>"c17=1",
		"|--S--SD VOSTFR"=>"c16=1",
		"|--S--Packs Anime"=>"c101=1",
		"|--S--Animes"=>"c32=1",
		"|--S--Anime VOSTFR"=>"c110=1",
		"|--S--Animation"=>"c123=1",
		"|--S--DOC"=>"c109=1",
		"|--S--Sport"=>"c34=1",
		"|--S--Emission TV"=>"c30=1",
		"|--Livres"=>"c24=1&c124=1&c96=1&c99=1&c116=1&c102=1&c103=1",
		"|--L--Romans"=>"c24=1",
		"|--L--Audio Books"=>"c124=1",
		"|--L--Magazines"=>"c96=1",
		"|--L--Bandes dessinées "=>"c99=1",
		"|--L--Romans Jeunesse"=>"c116=1",
		"|--L--Comics"=>"c102=1",
		"|--L--Mangas "=>"c103=1",
		"|--Logiciels"=>"c25=1&c27=1&c111=1&c26=1&c112=1&c28=1&c29=1&c117=1&c21=1&c22=1&c23=1",
		"|--L--Jeux PC"=>"c25=1",
		"|--L--Playstation"=>"c27=1",
		"|--L--Jeux MAC"=>"c111=1",
		"|--L--XboX"=>"c26=1",
		"|--L--Jeux Linux"=>"c112=1",
		"|--L--Nintendo"=>"c28=1",
		"|--L--NDS"=>"c29=1",
		"|--L--ROM"=>"c117=1",
		"|--L--Applis PC"=>"c21=1",
		"|--L--Applis Mac"=>"c22=1",
		"|--L--Smartphone"=>"c23=1"
	);
	protected static $seconds = array(
		"minute"=>60,
		"hour"	=>3600,
		"day"	=>86400,
		"week"	=>604800
	);
	protected static function getTime($now,$ago,$unit)
	{
		$delta = (array_key_exists($unit, self::$seconds) ? self::$seconds[$unit] : 0);
		return ($now - ($ago * $delta));
	}

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$url = 'https://xthor.tk';
		$now = time();
		if ($useGlobalCats)
			$categories = array( 'all' => 'c0=1',
				'movies' => 'c118=1&c119=1&c107=1&c1=1&c2=1&c100=1&c4=1&c5=1&c7=1&c3=1&c6=1&c8=1&c122=1&c94=1&c95=1&c12=1&c31=1&c33=1&c125=1&c20=1&c9=1',
				'tv' => 'c104=1&c13=1&c15=1&c14=1&c98=1&c17=1&c16=1&c123=1&c109=1&c34=1&c30=1',
				'games' => 'c25=1&c27=1&c111=1&c26=1&c112=1&c28=1&c29=1&c117=1&c23=1',
				'anime' => 'c101=1&c32=1&c110=1',
				'software' => 'c21=1&c22=1&c23=1',
				'books' => 'c24=1&c124=1&c96=1&c99=1&c116=1&c102=1&c103=1'
			);
		else
			$categories = &$this->categories;
		if (!array_key_exists($cat, $categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		$added = 0;
		$what = rawurlencode(rawurldecode($what));
		for ($pg = 0; $pg < 10; $pg++) {
			$cli = $this->fetch( $url . '/browse.php?' . $cat . '&searchin=title&incldead=1&sch=' . $what . '&sort=7&type=desc&page=' . $pg );
			if ( ($cli == false) || (strpos($cli->results, "Aucun résultat.") !== false)
				|| (strpos($cli->results, 'name="password') !== false) )
				break;
			$res = preg_match_all('`<td class=\'tdtor.*<img.*alt=\'(?P<cat>.*)\'.*<a href=\'details\.php\?id=(?P<id>\d+)\' onmouseover.*'.
				'<br \/><br \/><b>&nbsp;&nbsp;((?P<date>\d+ [a-zA-Z]{3} \d+)|.*(?P<ago_1>\d+) (?P<ago_2>minute|hour|day|week)s? ago)<\/b>.*onmouseout.*'.
				'<b>(?P<name>.*)<\/b>.*<a href="download.*>(?P<size>.*)<\/a>.*'.
				'<font.*>(?P<seeds>.*)<\/font>.*<font.*>(?P<leech>.*)<\/font>'.
				'`siU', $cli->results, $matches);
			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $url . '/download.php?torrent=' . $matches["id"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url . '/details.php?id=' . $matches["id"][$i];
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize($matches["size"][$i]);
						if($matches["ago_1"][$i])
							$item["time"] = self::getTime($now,$matches["ago_1"][$i],$matches["ago_2"][$i]);
						else
							$item["time"] = strtotime(self::removeTags($matches["date"][$i]));
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
