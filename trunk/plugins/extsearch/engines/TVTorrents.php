<?php

class TVTorrentsEngine extends commonEngine
{
	public $defaults = array( "public"=>false, "page_size"=>20, "auth"=>1 );

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://tvtorrents.com';
		for($pg = 1; $pg<11; $pg++)
		{
			$cli = $this->fetch( $url.'/loggedin/search.do?search='.$what.'&page='.$pg );

			if( ($cli==false) || (strpos($cli->results, "No torrents found.")!==false) 
				|| (strpos($cli->results, 'Password:<br>')!==false))
				break;
			$res = preg_match_all('`</td><td valign="top" width="70" style="padding:2px;"><span class="timeduration">\|(?P<date>\d*)</span></td>\r\n\t'.
				'<td valign="top" style="padding:2px;">(?P<name1>[^<]*)<br><a href="/loggedin/torrent\.do\?info_hash=(?P<id>[^"]*)">(?P<name2>[^<]*)</a></td>\r\n\t'.
				'<td width="40" valign="top" align="right" style="padding:2px;"><img style="float:left" src="/images/arrow_up_green_16\.png" title="Torrent has [^"]*">(?P<seeds>\d*)<br><img style="float:left" src="/images/arrow_down_green_16\.png" title="Torrent has [^"]*">(?P<leech>\d*)</td>\r\n\t'.
				'<td width="18" valign="top" style="padding:2px;" title="Torrent is (?P<size>[^"]*)"><a href="" onClick="return loadTorrent'.
				'`siU', $cli->results, $matches);
			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$id = self::removeTags($matches["id"][$i]);
					$link = 'http://torrent.tvtorrents.com/FetchTorrentServlet?info_hash='.$id.'&digest=1f74d41f505ed61970a162172f2f1a8761e729a8&hash=51dd08a64cf345582ba3ed316c40c798ebf4aeaa';
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["desc"] = $url."/loggedin/torrent.do?info_hash=".$id;
						$item["name"] = self::removeTags($matches["name1"][$i].$matches["name2"][$i]);
						$item["size"] = self::formatSize($matches["size"][$i]);
						$item["time"] = self::removeTags($matches["date"][$i])/1000;
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