<?php

class BitMeTVEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>35, "cookies"=>"www.bitmetv.org|pass=XXX;uid=XXX" );
	public $categories = array( 'all'=>'', "(70s Shows)"=>"211", "(80s Shows)"=>"110", "(Adult Swim)"=>"269", "(Anime)"=>"86",
		"(Billiards-Snooker-Pool)"=>"199", "(British - UK Comedy)"=>"116", "(British - UK Drama)"=>"231", "(British Mystery)"=>"215",
		"(Canadian Comedy)"=>"260", "(Canadian TV)"=>"261", "(Cartoons)"=>"90", "(Discovery)"=>"301", "(Documentaries)"=>"101",
		"(Fantasy-Supernatural)"=>"195", "(Food TV)"=>"278", "(Game Shows)"=>"380", "(HGTV)"=>"378", "(Home and Garden)"=>"335",
		"(Kids TV)"=>"355", "(National Geographic)"=>"476", "(News)"=>"225", "(Nickelodeon)"=>"322", "(NZTV)"=>"361", "(OZTV)"=>"238",
		"(PBS)"=>"423", "(Poker)"=>"134", "(Portable TV Episodes)"=>"99", "(Reality TV - Competitive)"=>"102", "(Reality TV - Un-scripted)"=>"196",
		"(Sci Fi)"=>"95", "(Soaps)"=>"329", "(Stand-Up Comedy)"=>"87", "(Subtitles)"=>"210", "(Sweden Comedy)"=>"294", "(Sweden Drama)"=>"295",
		"(Talk Shows)"=>"197", "(Tech TV)"=>"296", "(Trailers)"=>"228", "(TV Movies)"=>"416", "(US Comedy)"=>"245", "(US Drama)"=>"246",
		"(Westerns)"=>"307", "(Wrestling)"=>"209", "(z- Other TV Episodes)"=>"70" );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://www.bitmetv.org';
		if($useGlobalCats)
			$categories = array( 'all'=>'');
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		for($pg = 0; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/browse.php?search='.$what.'&sort=seeders&d=DESC&incldead=0&page='.$pg."&cat=".$cat );
			if( ($cli==false) || (strpos($cli->results, "<h3>Nothing found!</h3>")!==false) ||
				(strpos($cli->results, "<tr><td><b>Password:</b></td>")!==false))
				break;
			$res = preg_match_all('/<img border="0" src=.* alt="(?P<cat>.*)" \/><\/a>'.
				'.*<a href="details.php\?id=(?P<id>\d+)&amp;hit=1".*>(?P<name>.*)<\/a><br><font size=1><br>(?P<date>.*)<\/font>.*'.
				'<td .*>.*href="download.php\/\d+\/(?P<tname>.*)".*<\/a><\/td>.*'.
				'<td .*>.*<\/td>.*<td .*>.*<\/td>.*<td .*>.*<\/td>.*'.
				'<td .*>(?P<size>.*)<\/td>'.
				'.*<td .*>.*<\/td>.*<td .*>(?P<seeds>.*)<\/td>.*<td .*>(?P<leech>.*)<\/td>/siU', $cli->results, $matches);
			if(($res!==false) && ($res>0) &&
				count($matches["id"])==count($matches["cat"]) &&
				count($matches["cat"])==count($matches["name"]) && 
				count($matches["name"])==count($matches["size"]) &&
				count($matches["size"])==count($matches["seeds"]) &&
				count($matches["seeds"])==count($matches["date"]) &&
				count($matches["seeds"])==count($matches["tname"]) &&
				count($matches["date"])==count($matches["leech"]) )
			{
				for($i=0; $i<count($matches["id"]); $i++)
				{
					$link = $url."/download.php/".$matches["id"][$i]."/".$matches["tname"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/details.php?id=".$matches["id"][$i].'&hit=1';
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize(str_replace("<br>"," ",$matches["size"][$i]));
						$tm = self::removeTags($matches["date"][$i]);
						if( (($pos1=strpos($tm,","))!==false) &&
						    (($pos2=strpos($tm," at"))!==false))
						    $item["time"] = strtotime(substr($tm,$pos1+2,$pos2-$pos1-2).substr($tm,$pos2+3));
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
