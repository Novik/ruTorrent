<?php

/*
 *@author AceP1983
 *@version $Id$
*/


class BitGAMEREngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>30, "auth"=>1 );
	public $categories = array( 'all'=>'', '(Burning/Ripping)'=>'&c80=1', '(Guides/DOX)'=>'&c81=1', '(Movies/TV/Video)'=>'&c83=1', '(Music)'=>'&c84=1', 'Android'=>'&c89=1', 'iPhone/iPod/iPad'=>'&c88=1', 'GBA'=>'&c77=1', '3DS'=>'&c90=1', 'DS'=>'&c78=1', 'Gamecube'=>'&c72=1', 'Wii'=>'&c74=1', 'PC-Linux'=>'&c87=1', 'PC-Mac'=>'&c86=1', 'PC-Windows'=>'&c79=1', 'PS2'=>'&c73=1', 'PS3'=>'&c75=1', 'PSP'=>'&c76=1', 'XBOX'=>'&c70=1', 'XBOX 360'=>'&c71=1' );



	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://www.bitgamer.su';
		if($useGlobalCats)
			$categories = array( 'all'=>'', 'tv'=>'&c83=1', 'music'=>'&c84=1', 
				'games'=>'&c90=1&c89=1&c88=1&c77=1&c78=1&c72=1&c74=1&87=1&c86=1&c79=1&c73=1&c75=1&c76=1&c70=1&c71=1', 
				'software'=>'&c80=1', 'books'=>'&c81=1' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		for($pg = 0; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/browse.php?search='.$what.'&searchtitle=1&sort=seeders&d=DESC&incldead=0&page='.$pg.$cat );
			if( ($cli==false) || (strpos($cli->results, "<h3>Nothing found!</h3>")!==false)
				|| (strpos($cli->results, '<form id="loginform" method="post"')!==false))
				break;
			$res = preg_match_all("/<img class=.* border='0' src=.* alt='(?P<cat>.*)' \/><\/a>".
				".*<a href='details.php\?id=(?P<id>\d+)&amp;hit=1'.*>(?P<name>.*)<\/a>.*".
				"<td .*>.*<\/td><td .*><a class='index' href='download.php\?id=\d+&amp;name=(?P<tname>.*)'>.*".
				'<td .*>.*<\/td>.*'.
				'<td .*>.*<\/td>.*'.
				'<td .*>.*<\/td>.*'.
				'<td .*>(?P<date>.*)<\/td>.*'.
				'<td .*>.*<\/td>.*'.
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
					$link = $url."/download.php?id=".$matches["id"][$i]."&name=".$matches["tname"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/details.php?id=".$matches["id"][$i].'&hit=1';
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize($matches["size"][$i]);
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

?>