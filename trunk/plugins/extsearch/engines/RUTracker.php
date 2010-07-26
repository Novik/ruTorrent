<?php

class RUTrackerEngine extends commonEngine
{
       	public $defaults = array( "public"=>false, "page_size"=>50, "auth"=>1 );

	public $categories = array( 'all'=>"&f[]=-1" );

	protected function parseTList($results,&$added,&$ret,$limit)
	{
		if( strpos($results, ">�� �������</td>")!==false )
			break;
		$res = preg_match_all('/<a class="gen f" href="tracker\.php\?f=\d+">(?P<cat>.*)<\/a><\/td>.*'.
			'<a class="med tLink" href="\.\/viewtopic\.php\?t=(?P<id>\d+)"><b>(?P<name>.*)<\/b><\/a>.*'.
			'<u>(?P<size>.*)<\/u>\n\s*'.
			'<a class="small tr-dl dl-stub" href="(?P<link>.*)">.*'.
			'<td class="row4 seedmed"><b>(?P<seeds>.*)<\/b><\/td>\n\s*'.
			'<td class="row4 leechmed" title=".*"><b>(?P<leech>.*)<\/b><\/td>.*'.
			'<u>(?P<date>.*)<\/u>/siU', $results, $matches);
		if($res)
		{
			for($i=0; $i<$res; $i++)
			{
				$link = $matches["link"][$i];
				if(!array_key_exists($link,$ret))
				{
					$item = $this->getNewEntry();
					$item["cat"] = self::toUTF(self::removeTags($matches["cat"][$i],"CP1251"),"CP1251");
					$item["desc"] = "http://rutracker.org/forum/viewtopic.php?t=".$matches["id"][$i];
					$item["name"] = self::toUTF(self::removeTags($matches["name"][$i],"CP1251"),"CP1251");
					$item["size"] = floatval($matches["size"][$i]);
					$item["time"] = floatval($matches["date"][$i]);
					$item["seeds"] = intval(self::removeTags($matches["seeds"][$i]));
					$item["peers"] = intval(self::removeTags($matches["leech"][$i]));
					$ret[$link] = $item;
					$added++;
					if($added>=$limit)
						return(false);
				}
			}
			return(true);
		}
		else
			return(false);
	}

	public function action($what,$cat,&$ret,$limit,$useGlobalCats)
	{
		$added = 0;
		$url = 'http://rutracker.org';
		if($useGlobalCats)
			$categories = array( 'all'=>'&f[]=-1' );
		else
			$categories = &$this->categories;
		if(!array_key_exists($cat,$categories))
			$cat = $categories['all'];
		else
			$cat = $categories[$cat];
		$what = rawurlencode(self::fromUTF(rawurldecode($what),"CP1251"));
		$cli = $this->fetch( $url.'/forum/tracker.php', 0, "POST", "application/x-www-form-urlencoded", 
			'prev_my=0&prev_new=0&prev_oop=1'.$cat.'&o=10&s=2&oop=1&nm='.$what.'&submit=%CF%EE%E8%F1%EA' );
		if(($cli!==false) && $this->parseTList($cli->results,$added,$ret,$limit))
		{
			$res = preg_match_all('/<a class="pg" href="tracker.php\?search_id=(?P<next>[^"]*)">/siU', $cli->results, $next);
			$next = array_unique($next["next"]);
			for($pg = 0; $pg<count($next); $pg++)	
			{
				$cli = $this->fetch( $url.'/forum/tracker.php?search_id='.self::removeTags($next[$pg]) );
				if(($cli==false) || !$this->parseTList($cli->results,$added,$ret,$limit))
					break;
			}
		}
	}
}
?>