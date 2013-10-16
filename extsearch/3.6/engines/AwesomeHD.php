<?php

class AwesomeHDEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>50, "cookies"=>"awesome-hd.net|session=XXX" );
	public $categories = array( 'all'=>'', 'Movies'=>'&filter_cat[1]=1', 'TV-Shows'=>'&filter_cat[2]=1' );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://awesome-hd.net';
		if($useGlobalCats)
			$categories = array( 'all'=>'', 'movies'=>'&filter_cat[1]=1', 'tv'=>'&filter_cat[2]=1' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];

		for($pg = 1; $pg<11; $pg++)
		{
			$itemsFound = false;
			$cli = $this->fetch( $url.'/torrents.php?searchstr='.$what.$cat.'&action=basic&order_by=seeders&order_way=desc&page='.$pg );			
			if( ($cli==false) || (strpos($cli->results, "<h2>Your search did not match anything.</h2>")!==false) ||
				(strpos($cli->results, "<td>Password&nbsp;</td>")!==false))
				break;

			$res = preg_match_all('/<tr class="group">.*<td class="center">.*'.
				'<div title="View" id="showimg_(?P<id>\d+)".*'.
				'<td class="center cats_col">.*<div title="(?P<cat>.*)".*'.
				'<td colspan="2">(?P<name>.*)<span/siU', $cli->results, $matches);

			if(($res!==false) && ($res>0) &&
				count($matches["id"])==count($matches["name"]))
			{
				$groups = array();
                                for($i=0; $i<count($matches["id"]); $i++)
					$groups[intval($matches["id"][$i])] = array( "name" => self::removeTags($matches["name"][$i]), "cat" => self::removeTags($matches["cat"][$i]) );

				$res = preg_match_all('/<tr class="group_torrent groupid_(?P<id>\d+)">'.
					'.*<td colspan="3">.*'.
					'\[<a href="torrents\.php\?(?P<link>.*)" title="Download">DL<\/a>.*'.
					'<a href="torrents\.php\?id=(?P<desc>.*)">(?P<name>.*)<\/a>.*'.
					'<td class="nobr"><span title="(?P<date>.*)">.*<\/span><\/td>.*'.
					'<td class="nobr">(?P<size>.*)<\/td>.*'.
					'<td.*>.*<\/td>.*<td.*>(?P<seeds>.*)<\/td>.*'.
					'<td.*>(?P<leech>.*)<\/td>/siU', $cli->results, $matches);
				if(($res!==false) && ($res>0) &&
					count($matches["id"])==count($matches["link"]) && 
					count($matches["link"])==count($matches["desc"]) &&
					count($matches["desc"])==count($matches["name"]) &&
					count($matches["name"])==count($matches["date"]) &&
					count($matches["name"])==count($matches["size"]) &&
					count($matches["name"])==count($matches["seeds"]) &&
					count($matches["name"])==count($matches["leech"]))
				{
					$itemsFound = true;
					for($i=0; $i<count($matches["link"]); $i++)
					{
						$link = $url."/torrents.php?".self::removeTags($matches["link"][$i]);
						if(!array_key_exists($link,$ret))
						{
							$item = $this->getNewEntry();
							$item["desc"] = $url."/torrents.php?id=".self::removeTags($matches["desc"][$i]);
							$item["size"] = self::formatSize($matches["size"][$i]);
							$item["time"] = strtotime(self::removeTags($matches["date"][$i]));
							$item["seeds"] = intval(self::removeTags($matches["seeds"][$i]));
							$item["peers"] = intval(self::removeTags($matches["leech"][$i]));
							$grp = intval($matches["id"][$i]);
							if(array_key_exists($grp,$groups))
							{
								$item["cat"] = $groups[$grp]["cat"];
								$item["name"] = $groups[$grp]["name"].self::removeTags($matches["name"][$i]);
							}
							else
								$item["name"] = self::removeTags($matches["name"][$i]);

							$ret[$link] = $item;
							$added++;
							if($added>=$limit)
								return;
						}
					}
				}
			}
			if(!$itemsFound)
				break;
		}
	}
}
