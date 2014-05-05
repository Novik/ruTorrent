<?php

class TorrentzEngine extends commonEngine
{
	public $defaults = array( "public"=>true, "page_size"=>50 );
	public $categories = array( 'all'=>'', 'Movies'=>' movies', 'TV'=>' tv', 'Music'=>' music', 'Software'=>' software', 'Games'=>' games', 'XXX'=>' xxx');

	public function makeClient($url)
	{
		$client = parent::makeClient($url);
		$client->cookies = array("lw"=>"s");
		return($client);
	}
	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://torrentz.eu';
		if($useGlobalCats)
			$categories = array( 'all'=>'', 'movies'=>' movies', 'tv'=>' tv', 'music'=>' music', 'games'=>' games', 'anime'=>' anime', 'software'=>' software', 'pictures'=>' pictures', 'books'=>' books' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];

		$maxPage = 10;
		$updateMaxPage = true;
		for($pg = 0; $pg<$maxPage; $pg++)
		{
			$cli = $this->fetch( $url.'/search?q='.$what.$cat.'&p='.$pg );
			if($cli===false)
				break;

			// max page
			if ($updateMaxPage)
			{
				$updateMaxPage = false;
				if(!preg_match('`<a href="/search\?q=[^"]*&amp;p=\d+">(?P<maxpage>\d+)</a> <a href="/search\?q=[^"]*&amp;p=\d+">Next &raquo;</a>`siU',$cli->results, $matches))
					$maxPage = 0;
				else
					$maxPage = $matches["maxpage"];
			}
			// torrents
			$res = preg_match_all('`<dl><dt.*><a href="/(?P<hash>[0-9a-fA-F]+?)">(?P<name>.+)</a> &#187; '.
				'(?P<cat>.*)</dt><dd>.*'.
				'<span class="a"><span title="(?P<date>.*)">.*</span></span>'.
				'<span class="s">(?P<size>.*)</span> <span class="u">(?P<seeds>.*)</span>'.
				'<span class="d">(?P<leech>.*)</span></dd></dl>'.
				'`siU', $cli->results, $matches);

			if($res)
			{
				for($i=0; $i<$res; $i++)
				{
					$link = "http://torcache.net/torrent/".strtoupper($matches["hash"][$i]).".torrent";
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						$item["cat"] = self::removeTags($matches["cat"][$i]);
						$item["desc"] = $url."/".$matches["hash"][$i];
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize($matches["size"][$i]);
						$item["seeds"] = intval($matches["seeds"][$i]);
						$item["peers"] = intval($matches["leech"][$i]);
						$tms = self::removeTags($matches["date"][$i]);
						$tm = strptime($tms, '%a, %d %b %Y %T');
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
