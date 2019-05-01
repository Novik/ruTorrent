<?php

class NNMClubEngine extends commonEngine
{
       	public $defaults = array( "public"=>false, "page_size"=>50, "auth"=>1 );
	public $url = 'https://nnmclub.to';
	public $categories = array( 'all'=>"&f[]=-1" );

	protected function parseTList($results,&$added,&$ret,$limit)
	{
		if( strpos($results, ">Не найдено</td>")!==false )
			return(false);
		$res = preg_match_all('/<a class="gen" href="tracker\.php\?f=\d+&nm=[^"]*">(?P<cat>.*)<\/a><\/td>.*'.
			'class="genmed topictitle" href="viewtopic\.php\?t=(?P<id>\d+)">(?P<name>.*)<\/a>.*'.
			'href="download.php(?P<link>[^"]*)".*'.
			'class="gensmall"><u>(?P<size>.*)<\/u>.*'.
			'class="seedmed">(?P<seeds>.*)<\/td>.*'.
			'class="leechmed">(?P<leech>.*)<\/td>.*'.
			'<u>(?P<date>.*)<\/u>'.
			'/siU', $results, $matches);
		if($res)
		{
			for($i=0; $i<$res; $i++)
			{
				$link = $this->url.'/forum/download.php'.$matches["link"][$i];
				if(!array_key_exists($link,$ret))
				{
					$item = $this->getNewEntry();
					$item["cat"] = self::toUTF(self::removeTags($matches["cat"][$i],"CP1251"),"CP1251");
					$item["desc"] = $this->url."/forum/viewtopic.php?t=".$matches["id"][$i];
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
		$cat = '&f[]=-1';
		$what = rawurlencode(self::fromUTF(rawurldecode($what),"CP1251"));
//		$cli = $this->fetch( $this->url.'/forum/tracker.php' ); // just for login
		$cli = $this->fetch( $this->url.'/forum/tracker.php', 0, "POST", "application/x-www-form-urlencoded",
			'prev_sd=1&prev_a=1&prev_my=0&prev_n=0&prev_shc=0&prev_shf=1&prev_sha=1&prev_shs=0&prev_shr=0&prev_sht=0'.$cat.'&o=1&s=2&tm=-1&a=1&sd=1&shf=1&sha=1&ta=-1&sns=-1&sds=-1&nm='.$what.'&pn=&submit=%CF%EE%E8%F1%EA' );
		if(($cli!==false) && $this->parseTList($cli->results,$added,$ret,$limit))
		{
			$res = preg_match_all('/<a href="tracker.php\?search_id=(?P<next>[^&]*)&/siU', $cli->results, $next);
//			$next = array_unique($next["next"]);
			$next = $next["next"];
			for($pg = 0; $pg<count($next); $pg++)
			{
				$cli = $this->fetch( $this->url.'/forum/tracker.php?search_id='.self::removeTags($next[$pg]).'&nm='.$what.'&start='.(50*($pg+1)) );
				if(($cli==false) || !$this->parseTList($cli->results,$added,$ret,$limit))
					break;
			}
		}
	}
}
