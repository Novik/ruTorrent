<?php

class WhatCDEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>50, "auth"=>1 );
	public $categories = array( 'all'=>'', 'Music'=>'&filter_cat[1]=1', 'Applications'=>'&filter_cat[2]=1', 'E-Books'=>'&filter_cat[3]=1', 
		'Audiobooks'=>'&filter_cat[4]=1', 'E-Learning Videos'=>'&filter_cat[5]=1', 'Comedy'=>'&filter_cat[6]=1', 'Comics'=>'&filter_cat[7]=1' );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://what.cd';
		if($useGlobalCats)
			$categories = array( 'all'=>'', 'music'=>'&filter_cat[1]=1', 'software'=>'&filter_cat[2]=1', 'books'=>'&filter_cat[3]=1' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];

		for($pg = 1; $pg<10; $pg++)
		{
			$itemsFound = false;
			$cli = $this->fetch( $url.'/torrents.php?searchstr='.$what.'&tags_type=1&order_by=seeders&order_way=desc&page='.$pg.$cat );
			if( ($cli==false) || (strpos($cli->results, "<h2>Your search did not match anything.</h2>")!==false) ||
				(strpos($cli->results, "<td>Password&nbsp;</td>")!==false))
				break;

			$res = preg_match_all('/<tr class="torrent">.*<div title="(?P<cat>.*)".*<\/div>.*'.
				'\[ <a href="torrents.php\?(?P<link>.*)" title="Download">DL<\/a>.*'.
				'<a href="torrents.php\?id=(?P<desc>\d+)".*">(?P<name>.*)<\/a>.*'.
				'<td class="nobr"><span class="time" title="(?P<date>.*)">.*<\/span><\/td>.*'.
				'<td class="nobr">(?P<size>.*)<\/td>.*'.
				'<td>.*<\/td>.*<td>(?P<seeds>.*)<\/td>.*<td>(?P<leech>.*)<\/td>'.
				'/siU', $cli->results, $matches);

			if($res)
			{
				$itemsFound = true;
				for($i=0; $i<$res; $i++)
				{
					$link = $url."/torrents.php?".self::removeTags($matches["link"][$i]);
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/torrents.php?id=".$matches["desc"][$i];
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize($matches["size"][$i]);
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

			$res = preg_match_all('/<tr class="group">.*<td class="center">.*'.
				'<div title="View" id="showimg_(?P<id>\d+)".*'.
				'<td class="center cats_col">.*<div title="(?P<cat>.*)".*'.
				'<td colspan="2">(?P<name>.*)<span'.
				'/siU', $cli->results, $matches);
			if($res)
			{
				$groups = array();
                                for($i=0; $i<$res; $i++)
					$groups[intval($matches["id"][$i])] = array( "name" => self::removeTags(trim($matches["name"][$i])), "cat" => self::removeTags($matches["cat"][$i]) );

				$res = preg_match_all('/<tr class="group_torrent groupid_(?P<id>\d+)[ "].*<td colspan="3">.*\[ <a href="torrents.php\?(?P<link>.*)" title="Download">DL<\/a>.*'.
					'<a href="torrents.php\?id=(?P<desc>.*)">(?P<name>.*)<\/a>.*'.
					'<td class="nobr"><span class="time" title="(?P<date>.*)">.*<\/span><\/td>.*'.
					'<td class="nobr">(?P<size>.*)<\/td>.*'.
					'<td>.*<\/td>.*<td>(?P<seeds>.*)<\/td>.*<td>(?P<leech>.*)<\/td>'.
					'/siU', $cli->results, $matches);					
				if($res)
				{
					$itemsFound = true;
					for($i=0; $i<$res; $i++)
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
