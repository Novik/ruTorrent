<?php

class BroadcasTheEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>50, "auth"=>1 );
	public $categories = array( 'all'=>'&action=basic', 
		'Season'=>'&action=advanced&filter_cat[2]=1',
		'Episode'=>'&action=advanced&filter_cat[1]=1',
		'SD'=>'&action=advanced&resolution=SD', 
		'SD+Season'=>'&action=advanced&resolution=SD&filter_cat[2]=1',
		'720p'=>'&action=advanced&resolution=720p', 
		'720p+Season'=>'&action=advanced&resolution=720p&filter_cat[2]=1',
		'1080p'=>'&action=advanced&resolution=1080p', 
		'1080p+Season'=>'&action=advanced&resolution=1080p&filter_cat[2]=1',
		'1080i'=>'&action=advanced&resolution=1080i', 
		'1080i+Season'=>'&action=advanced&resolution=1080i&filter_cat[2]=1',
		'Portable'=>'&action=advanced&resolution=Portable Device', 
		'Portable+Season'=>'&action=advanced&resolution=Portable Device&filter_cat[2]=1',
		);

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://broadcasthe.net';
		if($useGlobalCats)
			$categories = array( 'all'=>'&action=basic' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		$sleepTime = 1;
		for($pg = 1; $pg<11; $pg++)
		{
			$cli = $this->fetch( $url.'/torrents.php?searchstr='.$what.'&artistname='.$what.$cat.'&searchtags=&tags_type=0&order_by=s6&order_way=desc&page='.$pg );
			if( ($cli==false) || (strpos($cli->results, "No search results")!==false)
				|| (strpos($cli->results, '<form name="loginform" id="loginform" method="post"')!==false))
				break;

			if(strpos($cli->results, '>Browse quota exceeded<')!==false)
			{
				$sleepTime = $sleepTime*1.5;
				if( $sleepTime > 5 )
					break;
				sleep( intval($sleepTime) );
				$pg--;
				continue;
			}
			else
				$sleepTime = 1;
			$res = preg_match_all('`<tr class="torrent">.*<img src="[^"]*" alt="(?P<cat>[^"]*)".*</td>.*'.
				'<a href="torrents.php\?action=download(?P<link>[^"]*)" title="Download">.*'.
                		'<a href="torrents.php\?id=(?P<desc>[^"]*)" title="View Torrent".*'.
		             	'<b>Added:</b>(?P<date>.*)(\- <b>Pre|</div>).*'.
				'<b>Release Name</b>: <span title="(?P<name>[^"]*)" style.*'.
				'<td>.*</td>.*'.
				'<td class="nobr">(?P<size>[^<]*)</td>.*'.
				'<td>.*</td>.*'.
				'<td>(?P<seeds>[^<]*)</td>.*'.
				'<td>(?P<leech>[^<]*)</td>.'.
				'`siU', $cli->results, $matches);
			if($res)
			{
				for($i=0; $i<$res; $i++)
				{

					$link = $url."/torrents.php?action=download".self::removeTags($matches["link"][$i]);
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/torrents.php?id=".self::removeTags($matches["desc"][$i]);
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize($matches["size"][$i]);
						$item["time"] = strtotime(trim(self::removeTags($matches["date"][$i])));
						if(empty($item["time"]))
							$item["time"] = time();
						$item["seeds"] = intval(self::removeTags(str_replace(",","",$matches["seeds"][$i])));
						$item["peers"] = intval(self::removeTags(str_replace(",","",$matches["leech"][$i])));
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
