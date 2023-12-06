<?php
class KATEngine extends commonEngine
{
	public $defaults = array( "public"=>true, "page_size"=>150 );
	public $url = 'https://katcr.co';
	public $categories = array(
		'all'=>'',
		'Movies'=>'Movies',
		'TV'=>'TV',
		'Music'=>'Music',
		'Games'=>'Games',
		'Books'=>'Books',
		'Apps'=>'Apps',
		'Anime'=>'Anime',
		'Other'=>'Other',
		'XXX'=>'XXX'
	);

 	public function fetch($url, $method="GET", $cookie="", $content_type="", $body="")
 	{
		$client = $this->makeClient($url);
		if($cookie)
			$client->cookies = $cookie;
		$client->fetchComplex( $url, $method, $content_type, $body );
		if($client->status>=200 && $client->status<300)
		{
			if(!$cookie)
			{
				foreach($client->headers as $header)
					if(preg_match("/^set-cookie:[\s]+([^=]+)=([^;]+)/i", $header, $match))
						$client->cookies[$match[1]] = $match[2];
				return($client->cookies);
			}
			else
			{
				ini_set( "pcre.backtrack_limit", max(strlen($client->results),100000) );
				return($client);
			}
		}
		return(false);
 	}

	public function get_cookie()
	{
		$gck = $this->fetch( $this->url . '/katsearch/page/1/' );
		return($gck ? $gck : false);
    	}

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$cookie = self::get_cookie();
		if($cookie)
		{
			$added = 0;
			if($useGlobalCats)
				$categories = array( 'all'=>'', 'movies'=>'Movies', 'tv'=>'TV', 'music'=>'Music', 'games'=>'Games', 'anime'=>'Anime', 'software'=>'Apps', 'books'=>'Books' );
			else
				$categories = &$this->categories;
			if(!array_key_exists($cat,$categories))
				$cat = $categories['all'];
			else
				$cat = $categories[$cat];

			// Website uses 1 page to display torrents

			// Use custom-search query (beta) - 500 results max.
			//	$cli = $this->fetch( $this->url . '/advanced-usearch/', "POST", $cookie,
			//		"application/x-www-form-urlencoded", 'category=' . $cat . '&orderby=seeds-desc&search=' . $what );

			// Use standard search because custom-search is temp disabled - 150 results max.
				$cli = $this->fetch( $this->url . '/katsearch/page/1/' . $what, "GET", $cookie );

			if( ($cli == false) || (strpos($cli->results, "<div class=\"torrents_table__torrent_name\">") === false)
				|| (strpos($cli->results, "loading........") !== false) )
				return;

			$res = preg_match_all(
				'`<a class="torrents_table__torrent_title" href="(?P<desc>.*)">(?P<name>.*)</a>.*'.
				'<a href=".*/category/.*>(?P<cat1>.*)</a>.*<a .*>(?P<cat2>.*)</a>.*'.
				'<a .* href="magnet:(?P<link>[^"]*)".*'.
				'<td .*>(?P<size>.*)</td>.*<td .*>.*</td>.*'.
				'<td .* title="(?P<date>.*)">.*'.
				'<td .*>(?P<seeds>.*)</td>.*'.
				'<td .*>(?P<leech>.*)</td>'.
				'`siU', $cli->results, $matches );
			if($res)
			{
				for( $i=0; $i<$res; $i++)
				{
					$link = "magnet:". htmlspecialchars_decode($matches["link"][$i]);
					if(!array_key_exists($link,$ret))
					{
						$item = $this->getNewEntry();
						// $item["desc"] = $matches["desc"][$i];
						$item["desc"] = $this->url.$matches["desc"][$i];
						$item["cat"] = self::removeTags($matches["cat1"][$i].' > '.$matches["cat2"][$i]);
						$item["name"] = self::removeTags($matches["name"][$i]);
						$item["size"] = self::formatSize(trim($matches["size"][$i]));
						$item["time"] = strtotime($matches["date"][$i]);
						$item["seeds"] = intval($matches["seeds"][$i]);
						$item["peers"] = intval($matches["leech"][$i]);
						$ret[$link] = $item;
						$added++;
						if($added>=$limit)
							return;
					}
				}
			}
			else
				return;
		}
	}
}
