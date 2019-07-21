<?php

class PirateBayEngine extends commonEngine
{
	public $defaults = array( "public"=>true, "page_size"=>30 );
	public $categories = array( 'all'=>'100,200,300,400,500,600', 'Audio'=>'100', 'Video'=>'200', 'Applications'=>'300', 'Games'=>'400', 'Porn'=>'500', 'Other'=>'600' );

	public function makeClient($url)
	{
		$client = parent::makeClient($url);
		return($client);
	}
	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'https://thepiratebay.org';
		if($useGlobalCats)
			$categories = array( 'all'=>'100,200,300,400,500,600', 'movies'=>'200', 'tv'=>'205', 'music'=>'100', 'games'=>'400', 'anime'=>'0', 'software'=>'300', 'pictures'=>'603', 'books'=>'601' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		$maxPage = 10;
		for($pg = 0; $pg<$maxPage; $pg++)
		{
			$cli = $this->fetch( $url . '/search/' . $what . '/' . $pg . '/7/' . $cat );
			if(($cli==false) || (strpos($cli->results, "</span>&nbsp;No hits.")!==false))
				break;
			$res = preg_match_all('`<td class="vertTh">.*'.
				'<a .*>(?P<cat>.*)</a>.*<a .*>(?P<subcat>.*)</a>.*'.
				'<a href="(?P<desc>.*)" .*>(?P<name>.*)</a>.*'.
				'<a href="magnet:(?P<link>[^"]*)" .*>.*<font .*>Uploaded (?P<date>.*), Size (?P<size>.*), .*</font>.*'.
				'<td align="right">(?P<seeds>.*)</td>.*'.
				'<td align="right">(?P<leech>.*)</td>'.
				'`siU', $cli->results, $matches);
			if($res)
			{
				for($i=0; $i<count($matches["link"]); $i++)
				{
					$link = "magnet:".$matches["link"][$i];
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i].' > '.$matches["subcat"][$i]);
						$item["desc"] = $url.$matches["desc"][$i];
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize($matches["size"][$i]);
						$item["seeds"] = intval(self::removeTags($matches["seeds"][$i]));
						$item["peers"] = intval(self::removeTags($matches["leech"][$i]));

						$tms = self::removeTags($matches["date"][$i]);
						if(strpos($tms,":")!==false)
						{
							$tm = strptime($tms,"%m-%d %H:%M");
							if($tm===false)
							{
								$tms = str_replace( "Y-day", "-1 day", $tms );
								$tm = strtotime($tms);
								if($tm!==false)
									$tm = localtime($tm,true);
							}
							else
								$tm["tm_year"] = date("Y")-1900;
						}
						else
						{
							if(preg_match( '/^(\d+) mins? ago$/i', $tms, $match ))
							{
								$tms = "-".$match[1]." minute";
								$tm = strtotime($tms);
								if($tm!==false)
									$tm = localtime($tm,true);
							}
							else
								$tm = strptime($tms,"%m-%d %Y");
						}

						if($tm!==false)
							$item["time"] = mktime(	$tm["tm_hour"], $tm["tm_min"], $tm["tm_sec"], $tm["tm_mon"]+1, $tm["tm_mday"], $tm["tm_year"]+1900 );
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
