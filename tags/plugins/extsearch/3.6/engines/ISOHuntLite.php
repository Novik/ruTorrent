<?php

class ISOHuntLiteEngine extends commonEngine
{
	public $defaults = array( "public"=>true, "page_size"=>100 );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$url = 'http://isohunt.com';
		$added = 0;
		for($pg = 0; $pg<10; $pg++)
		{
			$cli = $this->fetch( $url.'/js/json.php?rows=100&sort=seeds&start='.($pg*100+1).'&ihq='.$what );
			if($cli!==false)
			{
				$res = preg_match_all('/\{"title":"(?P<name>.*)","link":"(?P<desc>.*)",.*'.
					',"enclosure_url":"(?P<link>.*)",'.
					'"length":(?P<size>.*),.*'.
					',"category":"(?P<cat>.*)",.*,"Seeds":(?P<seeds>.*),"leechers":(?P<leech>.*),.*,"pubDate":"(?P<date>.*)"\}'.
					'/siU', $cli->results, $matches);
				if($res)
				{
					for($i=0; $i<$res; $i++)
					{
						if(!array_key_exists($matches["link"][$i],$ret) && !empty($matches["name"][$i]))
						{
							$item = $this->getNewEntry();
							$item["cat"] = self::removeTags($matches["cat"][$i]);
							$item["desc"] = str_replace("\\","",$matches["desc"][$i]);
							$item["name"] = self::fromJSON(self::removeTags($matches["name"][$i]));
							$item["size"] = $matches["size"][$i];
							$item["time"] = strtotime($matches["date"][$i]);
							$item["seeds"] = intval($matches["seeds"][$i]);
							$item["peers"] = intval($matches["leech"][$i]);
							$ret[$matches["link"][$i]] = $item;
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
}
