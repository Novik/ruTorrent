<?php

class JPopsukiEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>50, "auth"=>1 );
	public $categories = array(
		'All'=>'',
		"Album"=>"&filter_cat[1]=1",
		"Single"=>"&filter_cat[2]=1",
		"PV"=>"&filter_cat[3]=1",
		"DVD"=>"&filter_cat[4]=1",
		"TV-Music"=>"&filter_cat[5]=1",
		"TV-Variety"=>"&filter_cat[6]=1",
		"TV-Drama"=>"&filter_cat[7]=1",
		"Fansubs"=>"&filter_cat[8]=1",
		"Pictures"=>"&filter_cat[9]=1",
		"Misc"=>"&filter_cat[10]=1"
		);

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://jpopsuki.eu';
		if($useGlobalCats)
			$categories = array(
				'all'=>'',
				'tv'=>'&filter_cat[5]=1&filter_cat[6]=1&filter_cat[7]=1',
				'music'=>'&filter_cat[1]=1&filter_cat[2]=1&filter_cat[3]=1&filter_cat[4]=1&filter_cat[5]=1',
				'pictures'=>'&filter_cat[9]=1'
				 );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];

		for($pg = 1; $pg<11; $pg++)
		{
			$cli = $this->fetch( $url.'/torrents.php?searchstr='.$what.$cat.'&order_by=s6&order_way=DESC&disablegrouping=1&page='.$pg );
			sleep( 1 );
			if( ($cli==false) || (strpos($cli->results, ">Your search did not match anything.<")!==false) ||
				(strpos($cli->results, ">Password")!==false))
				break;
			$res = preg_match_all('`<tr class="torrent.*<a href=\'torrents\.php.*>(?P<cat>.*)</a></td>.*'.
				'<span>\[<a href="torrents\.php\?action=download&amp;(?P<link>.*)".*'.
				'(<a href="artist\.php\?id=.*>(?P<name1>.*))?'.
				'<a href="torrents\.php\?id=(?P<desc>.*)".*>(?P<name2>.*)<.*'.
				'<td.*>.*</td>.*'.
				'<td class="nobr" title="(?P<date>.*)".*</td>.*'.
				'<td class="nobr">(?P<size>.*)</td>.*'.
				'<td.*>.*</td>.*'.
				'<td.*>(?P<seeds>.*)</td>.*'.
				'<td.*>(?P<leech>.*)</td>'.
				'`siU', $cli->results, $matches);
			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = $url."/torrents.php?action=download&".self::removeTags($matches["link"][$i]);
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["desc"] = $url."/torrents.php?id=".self::removeTags($matches["desc"][$i]);
						$item["size"] = self::formatSize($matches["size"][$i]);
						$item["time"] = strtotime(self::removeTags($matches["date"][$i]));
						$item["seeds"] = intval(self::removeTags($matches["seeds"][$i]));
						$item["peers"] = intval(self::removeTags($matches["leech"][$i]));
						$item["name"] = self::removeTags($matches["name1"][$i].$matches["name2"][$i]);
						$item["cat"] = self::removeTags($matches["cat"][$i]);
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
