<?php

/*
 *@author AceP1983
*/
 

class BlackcatsGamesEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>50, "auth"=>1 );
	public $categories = array( 'all'=>'&cat=0', '3DS'=>'&cat=68', 'Android'=>'&cat=63', 'Audiobooks'=>'&cat=60', 'Console Apps'=>'&cat=66', 'Dreamcast'=>'&cat=7', 'DS'=>'&cat=8', 'Game-Dev'=>'&cat=69', 'Game-Dox|Saves'=>'&cat=30', 'Game-Mods'=>'&cat=64', 'Game-OST'=>'&cat=29', 'Gamecube'=>'&cat=5', 'iPhone|iPad'=>'&cat=36', 'JTAG|RGH'=>'&cat=45', 'Linux'=>'&cat=38', 'Mac'=>'&cat=33', 'Mame'=>'&cat=56', 'Member Creations'=>'&cat=44', 'NES|SNES'=>'&cat=53', 'Packs'=>'&cat=62', 'PC'=>'&cat=1', 'Phone|PDA'=>'&cat=35', 'PS1'=>'&cat=6', 'PS2'=>'&cat=2', 'PS3'=>'&cat=24', 'PS4'=>'&cat=75', 'PSN'=>'&cat=70', 'PSP'=>'&cat=9', 'PSXPSP'=>'&cat=32', 'Roms'=>'&cat=11', 'Saturn'=>'&cat=10', 'Tech Vids'=>'&cat=67', 'Wii'=>'&cat=25', 'WiiU'=>'&cat=72', 'WiiVC'=>'&cat=39', 'WiiWare'=>'&cat=40', 'XBOX'=>'&cat=3', 'XBOX 360'=>'&cat=4', 'XBOX One'=>'&cat=74', 'XBOXto360'=>'&cat=34' );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://www.blackcats-games.net';
		if($useGlobalCats)
			$categories = array( 'all'=>'&cat=0' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		for($pg = 0; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/browse.php?search='.$what.'&incldead=0&blah=0&sort=7&type=desc&page='.$pg.$cat );
			
			if( ($cli==false) || (strpos($cli->results, "<h2>Nothing found!</h2>")!==false) ||
				(strpos($cli->results, '<input class="post" type="password" name="password"')!==false))
				break;
			$res = preg_match_all('/<img border="0" src=.* alt="(?P<cat>.*)" \/><\/a>'.
				'.*<td .*>.*href="details.php\?id=(?P<id>\d+)&amp;hit=1".*>.*<b>(?P<name>.*)<\/b>.*<br\/>(?P<date>.*)<a href=download.php\/\d+\/(?P<tname>.*)>.*'.
				'<td .*>.*<\/td>'.
				'.*<td .*>(?P<size>.*)<\/td>'.
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
						$item["size"] = self::formatSize(str_replace("<br/>"," ",$matches["size"][$i]));
						$tm = self::removeTags($matches["date"][$i]);
						if( (($pos=strpos($tm,"-"))!==false))
						    $item["time"] = strtotime(substr($tm,0,$pos).substr($tm,$pos+2,5));
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
